<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Auth\OtpLoginController;
use App\Http\Resources\ConsumerResource;
use App\Http\Resources\UserResource;
use App\Models\Consumer;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    /**
     * Render the portal login page — consumer OTP login and official
     * credential login, Sahyog-portal styled.
     */
    public function showLogin(Request $request): View
    {
        if ($request->boolean('forget')) {
            $request->session()->forget(OtpLoginController::OTP_SESSION_KEYS);
        }

        return view('auth.login');
    }

    /**
     * Authenticate an official against the web session guard.
     */
    public function login(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ]);

        // The "email" field accepts a username or an email address; resolve
        // the lookup column from the value's shape.
        $login = $validated['email'];
        $loginColumn = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        $message = 'The provided credentials do not match our records.';

        if (Auth::guard('consumer')->check()) {
            Auth::guard('consumer')->logout();
        }

        if (Auth::attempt([$loginColumn => $login, 'password' => $validated['password']])) {
            $request->session()->regenerate();

            return $request->expectsJson()
                ? response()->json(['user' => new UserResource($request->user())])
                : redirect()->intended(route('dashboard'));
        }

        return $request->expectsJson()
            ? response()->json(['message' => $message], 422)
            : back()->withErrors(['email' => $message]);
    }

    /**
     * End all sessions — officials, consumers, or both.
     */
    public function logout(Request $request): RedirectResponse|JsonResponse
    {
        if (Auth::guard('consumer')->check()) {
            Auth::guard('consumer')->logout();
        }

        if (Auth::check()) {
            Auth::logout();
        }

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return $request->expectsJson()
            ? response()->json(['message' => 'Logged out.'])
            : redirect()->route('login');
    }

    /**
     * Return the authenticated party — a consumer or an official.
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => $request->user('consumer') instanceof Consumer
                ? new ConsumerResource($request->user('consumer'))
                : new UserResource($request->user()),
        ]);
    }
}
