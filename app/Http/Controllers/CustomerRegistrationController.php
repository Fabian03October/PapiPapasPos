<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\Request;

class CustomerRegistrationController extends Controller
{
    public function showForm()
    {
        return view('registro.formulario');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string',
            'birthdate' => 'nullable|date|before_or_equal:today|after:'.now()->subYears(110)->toDateString(),
        ]);

        $phone = preg_replace('/\D/', '', $validated['phone']);

        if (strlen($phone) !== 10) {
            return back()->withErrors(['phone' => 'El teléfono debe tener 10 dígitos.'])->withInput();
        }

        // Antes, con un teléfono ya registrado se mostraba la tarjeta (QR y
        // Google Wallet) de ESA persona: cualquiera podía quedarse con la
        // tarjeta de otro solo con su número. Ahora se le pide ir a caja.
        if (Customer::where('phone', $phone)->exists()) {
            return back()->withErrors([
                'phone' => 'Este teléfono ya está registrado. Si es tuyo, pide tu tarjeta en caja.',
            ])->withInput();
        }

        $customer = Customer::create(['name' => $validated['name'], 'phone' => $phone, 'birthdate' => $validated['birthdate'] ?? null]);

        // Directo a su tarjeta (con la bienvenida y cómo instalarla).
        return redirect()->route('mi-tarjeta', ['qrCode' => $customer->qr_code, 'nuevo' => 1]);
    }

    /** Enlace viejo (antes de "Mi tarjeta"): algunos clientes lo tienen guardado. */
    public function confirmation(string $qrCode)
    {
        $customer = Customer::where('qr_code', $qrCode)->firstOrFail();

        return redirect()->route('mi-tarjeta', $customer->qr_code, 301);
    }
}
