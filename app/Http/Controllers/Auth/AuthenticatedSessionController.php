<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View|RedirectResponse
    {
        if (Auth::check()) {
            $user = Auth::user();
            if ($user->isTechAdmin()) {
                return redirect()->route('tech.dashboard');
            }
            if ($user->isOwner()) {
                return redirect()->route('business.dashboard');
            }
        }

        return view('admin.auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(Request $request): RedirectResponse
    {
        $input = $request->validate([
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $user = \App\Models\User::where('email', $input['login'])->first();

        if ($user && !$user->is_active) {
            throw ValidationException::withMessages([
                'login' => 'Votre compte administrateur a été désactivé.',
            ]);
        }

        $credentials = [
            'email' => $input['login'],
            'password' => $input['password'],
        ];

        if (!Auth::attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'login' => __('auth.failed'),
            ]);
        }

        $request->session()->regenerate();

        $user = Auth::user();
        
        // Mettre à jour la date de dernière connexion
        $user->update(['last_login_at' => now()]);
        
        // Enregistrer dans l'audit log
        \App\Models\AuditLog::record(
            $user->id, 
            'login', 
            "Connexion de l'utilisateur {$user->name} au panneau d'administration", 
            'auth'
        );

        if ($user->isTechAdmin()) {
            return redirect()->intended(route('tech.dashboard', absolute: false));
        }

        if ($user->isOwner()) {
            return redirect()->intended(route('business.dashboard', absolute: false));
        }

        return redirect()->intended('/');
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $user = Auth::user();

        if ($user) {
            \App\Models\AuditLog::record(
                $user->id, 
                'logout', 
                "Déconnexion de l'utilisateur {$user->name}", 
                'auth'
            );
        }

        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/login');
    }
}
