<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class ChangePasswordController extends Controller
{
    /**
     * Render the consumer password change form reached from the portal sidebar.
     */
    public function create(Request $request): View
    {
        $consumer = $request->user('consumer');

        abort_unless($consumer !== null, 403);

        return view('auth.password-change', [
            'consumer' => $consumer,
        ]);
    }

    /**
     * Verify the current password, store the replacement, and refresh the session.
     */
    public function store(Request $request): RedirectResponse
    {
        $consumer = $request->user('consumer');

        abort_unless($consumer !== null, 403);

        // Consumers who signed in by OTP and never chose a password have nothing
        // to verify — this page doubles as their chance to set one. Everyone
        // else must confirm the current password first.
        $needsCurrentPassword = $consumer->password !== null;

        $validated = $request->validate([
            'current_password' => $needsCurrentPassword ? ['required', 'string'] : ['nullable'],
            'password' => ['required', 'confirmed', Password::min(8), 'different:current_password'],
        ]);

        if ($needsCurrentPassword && ! Hash::check($validated['current_password'], $consumer->password)) {
            return back()->withErrors([
                'current_password' => 'The current password does not match our records.',
            ]);
        }

        $consumer->forceFill(['password' => $validated['password']])->save();

        $request->session()->regenerate();

        return redirect()->route('password.change')->with('status', 'Password changed successfully.');
    }
}
