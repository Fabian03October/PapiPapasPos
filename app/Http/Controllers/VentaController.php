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
        $product->load(['variants', 'modifierGroups.modifiers']);

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
                ->sortBy('sort_order')
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

    public function findCustomerByQr(string $qrCode)
    {
        $customer = Customer::where('qr_code', $qrCode)->first();

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

        $customers = Customer::where('name', 'like', "%{$search}%")
            ->orWhere('phone', 'like', "%{$search}%")
            ->limit(10)
            ->get(['id', 'name', 'phone']);

        return response()->json($customers);
    }
}