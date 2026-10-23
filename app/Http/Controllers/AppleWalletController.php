<?php

namespace App\Http\Controllers;

use App\Models\AppleWalletPass;
use App\Models\AppleWalletRegistration;
use App\Models\Customer;
use App\Services\AppleWalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Apple Wallet: descarga del pase + el "Wallet Web Service" que Apple exige
 * para actualizar pases (rutas /wallet/apple/v1/..., sin sesión ni CSRF: las
 * llama el iPhone, no un navegador).
 *
 * https://developer.apple.com/documentation/walletpasses/adding-a-web-service-to-update-passes
 */
class AppleWalletController extends Controller
{
    public function __construct(protected AppleWalletService $apple) {}

    /** El .pkpass del cliente. Mismo enlace secreto (UUID del QR) que la página de registro. */
    public function download(string $qrCode)
    {
        abort_unless($this->apple->isEnabled(), 404);

        $customer = Customer::where('qr_code', $qrCode)->where('is_active', true)->firstOrFail();

        return $this->pkpassResponse($customer, $this->apple->passFor($customer));
    }

    /** POST v1/devices/{device}/registrations/{passType}/{serial} */
    public function register(Request $request, string $device, string $passType, string $serial)
    {
        $pass = $this->authorizedPass($request, $passType, $serial);
        $pushToken = (string) $request->json('pushToken');

        if ($pushToken === '' || strlen($pushToken) > 200) {
            return response('', 400);
        }

        $registration = AppleWalletRegistration::updateOrCreate(
            ['apple_wallet_pass_id' => $pass->id, 'device_library_identifier' => $device],
            ['push_token' => $pushToken],
        );

        return response('', $registration->wasRecentlyCreated ? 201 : 200);
    }

    /** DELETE v1/devices/{device}/registrations/{passType}/{serial} */
    public function unregister(Request $request, string $device, string $passType, string $serial)
    {
        $pass = $this->authorizedPass($request, $passType, $serial);

        AppleWalletRegistration::where('apple_wallet_pass_id', $pass->id)
            ->where('device_library_identifier', $device)
            ->delete();

        return response('', 200);
    }

    /** GET v1/devices/{device}/registrations/{passType}?passesUpdatedSince=tag — qué pases de este iPhone cambiaron. */
    public function serials(Request $request, string $device, string $passType)
    {
        abort_unless($this->apple->isEnabled() && $passType === $this->apple->passTypeIdentifier(), 404);

        $query = AppleWalletPass::whereHas('registrations', fn ($q) => $q->where('device_library_identifier', $device));

        if ($since = $request->query('passesUpdatedSince')) {
            $query->where('updated_at', '>', Carbon::createFromTimestamp((int) $since, config('app.timezone')));
        }

        $passes = $query->get(['serial_number', 'updated_at']);

        if ($passes->isEmpty()) {
            return response('', 204);
        }

        return response()->json([
            'serialNumbers' => $passes->pluck('serial_number')->values(),
            'lastUpdated' => (string) $passes->max('updated_at')->timestamp,
        ]);
    }

    /** GET v1/passes/{passType}/{serial} — el pase al día (304 si no cambió). */
    public function latest(Request $request, string $passType, string $serial)
    {
        $pass = $this->authorizedPass($request, $passType, $serial);
        $customer = $pass->customer;

        abort_unless($customer, 404);

        if ($since = $request->header('If-Modified-Since')) {
            try {
                if ($pass->updated_at->lte(Carbon::parse($since))) {
                    return response('', 304);
                }
            } catch (\Throwable) {
                // Fecha ilegible: se manda el pase completo.
            }
        }

        return $this->pkpassResponse($customer, $pass);
    }

    /** POST v1/log — errores que reporta el iPhone (útil al configurar). */
    public function log(Request $request)
    {
        Log::warning('Apple Wallet (iPhone): '.json_encode(array_slice((array) $request->json('logs'), 0, 20)));

        return response('', 200);
    }

    /** El pase de ese número de serie, solo si el iPhone trae su token ("ApplePass <token>"). */
    protected function authorizedPass(Request $request, string $passType, string $serial): AppleWalletPass
    {
        abort_unless($this->apple->isEnabled() && $passType === $this->apple->passTypeIdentifier(), 404);

        $pass = AppleWalletPass::where('serial_number', $serial)->first();
        $header = (string) $request->header('Authorization');

        if (! $pass || ! str_starts_with($header, 'ApplePass ')
            || ! hash_equals($pass->authentication_token, substr($header, strlen('ApplePass ')))) {
            abort(401);
        }

        return $pass;
    }

    protected function pkpassResponse(Customer $customer, AppleWalletPass $pass)
    {
        return response($this->apple->generate($customer), 200, [
            'Content-Type' => 'application/vnd.apple.pkpass',
            'Content-Disposition' => 'attachment; filename="papis-papas.pkpass"',
            'Last-Modified' => $pass->updated_at->toRfc7231String(),
            'Cache-Control' => 'no-store',
        ]);
    }
}
