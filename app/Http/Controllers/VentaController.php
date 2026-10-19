<?php

namespace App\Http\Controllers;

use App\Models\CashSession;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use Illuminate\Http\Request;

class VentaController extends Controller
{
    public function index()
    {
        if (! CashSession::open()) {
            return redirect()->route('caja.abrir');
        }

        $categories = Category::where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $products = Product::where('is_active', true)
            ->with('variants')
            ->get()
            ->groupBy('category_id');

        return view('venta.index', [
            'categories' => $categories,
            'products' => $products,
        ]);
    }

    public function productDetails(Product $product)
    {
        // Solo variantes/grupos/modificadores activos - desactivar algo
        // desde el panel debe quitarlo de la venta también, no solo del
        // panel.
        $product->load([
            'variants' => fn ($q) => $q->where('is_active', true),
            'modifierGroups' => fn ($q) => $q->where('is_active', true),
            'modifierGroups.modifiers' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')->orderBy('id'),
        ]);

        return response()->json([
            'id' => $product->id,
            'name' => $product->name,
            'base_price' => (float) $product->base_price,
            'variants' => $product->variants->map(fn ($v) => [
                'id' => $v->id,
                'name' => $v->name,
                'price_delta' => (float) $v->price_delta,
            ]),
            'modifier_groups' => $product->modifierGroups
                // Un grupo sin ningún modificador activo no sirve de nada
                // mostrarlo (y si era obligatorio, bloquearía la venta).
                ->filter(fn ($g) => $g->modifiers->isNotEmpty())
                // Obligatorios primero (la presentación: Domo, Charola...) y
                // luego el orden del admin. Mismo orden que la comanda.
                ->sortBy(fn ($g) => [$g->is_required ? 0 : 1, $g->sort_order, $g->id])
                ->values()
                ->map(fn ($g) => [
                    'id' => $g->id,
                    'name' => $g->name,
                    'min_select' => $g->min_select,
                    'max_select' => $g->max_select,
                    'is_required' => $g->is_required,
                    'modifiers' => $g->modifiers->map(fn ($m) => [
                        'id' => $m->id,
                        'name' => $m->name,
                        'price_delta' => (float) $m->price_delta,
                    ]),
                ]),
        ]);
    }

    public function clientePage()
    {
        return view('venta.cliente');
    }

    public function selectCustomer(Customer $customer)
    {
        session([
            'selected_customer_id' => $customer->id,
            'selected_customer_name' => $customer->name,
        ]);

        return redirect()->route('venta.index');
    }

    public function clearCustomer()
    {
        session()->forget(['selected_customer_id', 'selected_customer_name']);

        return redirect()->route('venta.index');
    }

    /** Reglas comunes para dar de alta o editar un cliente desde la venta. */
    protected function customerData(Request $request, ?Customer $customer = null): array
    {
        $request->merge(['phone' => preg_replace('/\D/', '', (string) $request->input('phone'))]);

        return $request->validate([
            'name' => 'required|string|max:255',
            'phone' => ['required', 'digits:10', \Illuminate\Validation\Rule::unique('customers', 'phone')->ignore($customer?->id)],
            'birthdate' => 'nullable|date|before_or_equal:today|after:' . now()->subYears(110)->toDateString(),
        ], [
            'phone.digits' => 'El teléfono debe tener 10 dígitos.',
            'phone.unique' => 'Ese teléfono ya es de otro cliente. Búscalo en "Buscar".',
        ]);
    }

    /** "+ Nuevo": registrar al cliente sin salir de la venta y dejarlo seleccionado. */
    public function storeCustomer(Request $request)
    {
        $customer = Customer::create($this->customerData($request));

        session(['selected_customer_id' => $customer->id, 'selected_customer_name' => $customer->name]);

        return response()->json(['success' => true, 'id' => $customer->id, 'name' => $customer->name]);
    }

    /** Corregir nombre, teléfono o cumpleaños del cliente desde la venta. */
    public function updateCustomer(Request $request, Customer $customer)
    {
        $customer->update($this->customerData($request, $customer));

        if ((int) session('selected_customer_id') === $customer->id) {
            session(['selected_customer_name' => $customer->name]);
        }

        return response()->json(['success' => true, 'id' => $customer->id, 'name' => $customer->name]);
    }

    public function showCustomer(Customer $customer)
    {
        return response()->json([
            'id' => $customer->id,
            'name' => $customer->name,
            'phone' => $customer->phone,
            'birthdate' => $customer->birthdate?->toDateString(),
        ]);
    }

    public function findCustomerByQr(string $qrCode)
    {
        $customer = Customer::where('qr_code', $qrCode)->where('is_active', true)->first();

        if (! $customer) {
            return response()->json(['found' => false], 404);
        }

        return response()->json([
            'found' => true,
            'id' => $customer->id,
            'name' => $customer->name,
            'phone' => $customer->phone,
        ]);
    }

    public function searchCustomers(Request $request)
    {
        $search = $request->query('q', '');

        $customers = Customer::where('is_active', true)
            ->where(function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            })
            ->limit(10)
            ->get(['id', 'name', 'phone']);

        return response()->json($customers);
    }
}