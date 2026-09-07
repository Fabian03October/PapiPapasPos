<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function showPinLogin()
    {
        return view('auth.pin-login');
    }

    public function loginPin(Request $request)
    {
        $request->validate([
            'pin' => 'required|digits:4',
        ]);

        $user = User::with('role')
            ->where('is_active', true)
            ->get()
            ->first(fn ($u) => Hash::check($request->pin, $u->pin));

        if (! $user) {
            return response()->json(['success' => false, 'message' => 'PIN incorrecto'], 422);
        }

        Auth::login($user);

        $redirect = $user->role?->name === 'manager'
            ? route('filament.admin.pages.dashboard')
            : route('venta.index');

        return response()->json([
            'success' => true,
            'name' => $user->name,
            'role' => $user->role->name ?? 'Sin rol',
            'redirect' => $redirect,
        ]);
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login.pin');
    }
}