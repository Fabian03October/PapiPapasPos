<?php

namespace App\Http\Controllers;

use App\Mail\PinResetMail;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class ForgotPinController extends Controller
{
    public function showRequestForm()
    {
        return view('auth.forgot-pin');
    }

    public function sendResetLink(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $user = User::where('email', $request->email)->where('is_active', true)->first();

        // Por seguridad, siempre respondemos igual, exista o no el correo
        if (! $user) {
            return response()->json(['success' => true]);
        }

        $token = Str::random(64);

        DB::table('pin_reset_tokens')->updateOrInsert(
            ['email' => $user->email],
            ['token' => Hash::make($token), 'created_at' => now()]
        );

        Mail::to($user->email)->send(new PinResetMail($user, $token));

        return response()->json(['success' => true]);
    }

    public function showResetForm(string $token, Request $request)
    {
        $email = $request->query('email');

        return view('auth.reset-pin', [
            'token' => $token,
            'email' => $email,
        ]);
    }

    public function resetPin(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'token' => 'required',
            'new_pin' => 'required|digits:4',
        ]);

        $record = DB::table('pin_reset_tokens')->where('email', $request->email)->first();

        if (! $record || ! Hash::check($request->token, $record->token)) {
            return response()->json(['success' => false, 'message' => 'Este enlace ya no es válido, pide uno nuevo.'], 422);
        }

        if (now()->diffInMinutes($record->created_at) > 60) {
            return response()->json(['success' => false, 'message' => 'Este enlace ya expiró, pide uno nuevo.'], 422);
        }

        $user = User::where('email', $request->email)->first();
        $user->pin = $request->new_pin;
        $user->must_change_pin = false;
        $user->save();

        DB::table('pin_reset_tokens')->where('email', $request->email)->delete();

        return response()->json(['success' => true]);
    }
}