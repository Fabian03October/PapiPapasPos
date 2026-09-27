<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Services\GoogleWalletService;
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
        ]);

        $phone = preg_replace('/\D/', '', $validated['phone']);

        if (strlen($phone) !== 10) {
            return back()->withErrors(['phone' => 'El teléfono debe tener 10 dígitos.'])->withInput();
        }

        $customer = Customer::where('phone', $phone)->first()
            ?? Customer::create(['name' => $validated['name'], 'phone' => $phone]);

        return redirect()->route('registro.confirmacion', $customer->qr_code);
    }

    public function confirmation(string $qrCode, GoogleWalletService $wallet)
    {
        $customer = Customer::where('qr_code', $qrCode)->firstOrFail();

        return view('registro.confirmacion', [
            'customer' => $customer,
            'walletSaveLink' => $wallet->generateSaveLink($customer),
        ]);
    }
}
