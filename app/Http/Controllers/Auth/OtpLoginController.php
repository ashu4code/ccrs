<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\ConsumerResource;
use App\Models\Consumer;
use App\Models\Otp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class OtpLoginController extends Controller
{
    /**
     * Session keys carrying a pending OTP sign-in — cleared together whenever
     * the sign-in succeeds, the OTP goes stale, or the step is reset manually.
     */
    public const OTP_SESSION_KEYS = ['otp.id', 'otp.masked_mobile', 'otp.dev_code'];

    /**
     * First step of consumer login: request an OTP for a registered consumer number.
     */
    public function request(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'consumer_number' => ['required', 'string', 'max:255'],
        ]);

        $consumer = Consumer::query()
            ->where('consumer_number', $validated['consumer_number'])
            ->first();

        if ($consumer === null) {
            $message = 'No registered consumer matches these details.';

            return $request->expectsJson()
                ? response()->json(['message' => $message], 422)
                : back()->withErrors([
                    'consumer_number' => $message,
                ]);
        }

        [$otp, $code] = Otp::issue($consumer);

        $this->deliver($consumer, $code);

        $request->session()->put([
            'otp.id' => $otp->getKey(),
            'otp.masked_mobile' => sprintf('%s %s', str_repeat('•', 6), Str::substr((string) $consumer->mobile, -4)),
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'A one-time password was sent to your registered mobile number.',
                'mobile_masked' => $request->session()->get('otp.masked_mobile'),
            ]);
        }

        return redirect()->route('home');
    }

    /**
     * Second step: verify the OTP, sign the consumer in, and route them to
     * their dashboard.
     */
    public function verify(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'digits:'.Otp::CODE_LENGTH],
        ]);

        $otpId = $request->session()->get('otp.id');

        $outcome = DB::transaction(function () use ($otpId, $validated): array {
            $otp = Otp::query()->with('consumer')->whereKey($otpId)->lockForUpdate()->first();

            if ($otp === null || $otp->isConsumed() || $otp->isExpired() || $otp->hasExhaustedAttempts()) {
                return ['stale' => true];
            }

            $otp->registerAttempt();

            if (! $otp->matches($validated['code'])) {
                return ['consumer' => null];
            }

            $otp->consume();

            return ['consumer' => $otp->consumer];
        });

        if (($outcome['stale'] ?? false) === true) {
            $request->session()->forget(self::OTP_SESSION_KEYS);

            return $this->otpRejection($request, 'This code is expired or fully attempted. Request a new one.');
        }

        if (! ($outcome['consumer'] instanceof Consumer)) {
            return $this->otpRejection($request, 'The code is incorrect. Check the SMS and try again.');
        }

        $consumer = $outcome['consumer'];

        if (Auth::check()) {
            Auth::logout();
        }

        Auth::guard('consumer')->login($consumer);

        $request->session()->regenerate();
        $request->session()->forget(self::OTP_SESSION_KEYS);

        // Every verified consumer lands on their dashboard. A first-time
        // password is optional — it can be chosen later from the sidebar's
        // Change Password page without re-entering any credentials.
        return $request->expectsJson()
            ? response()->json(['message' => 'Logged in.', 'user' => new ConsumerResource($consumer)])
            : redirect()->intended(route('dashboard'));
    }

    /**
     * Uniform rejection that serves both the browser page and JSON clients.
     */
    private function otpRejection(Request $request, string $message): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $message], 422);
        }

        return back()->withErrors([
            'code' => $message,
        ]);
    }

    /**
     * Deliver the code. In non-production environments the code is written to
     * the log and stored in the session for on-screen display — kept visible
     * across retries until an SMS gateway is integrated — and cleared together
     * with the pending sign-in (self::OTP_SESSION_KEYS).
     */
    private function deliver(Consumer $consumer, string $code): void
    {
        Log::info(sprintf('Login OTP for consumer %s: %s', $consumer->consumer_number, $code));

        if (! app()->isProduction()) {
            session()->put('otp.dev_code', $code);
        }
    }
}
