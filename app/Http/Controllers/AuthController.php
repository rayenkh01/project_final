<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Throwable;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function showForgotPassword()
    {
        return view('auth.forgot-password');
    }

    public function login(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
            'role' => ['required', Rule::in(User::roles())],
        ]);

        try {
            $user = User::query()
                ->whereRaw('LOWER(email) = ?', [strtolower($validated['email'])])
                ->first();

            if ($user && $this->passwordMatches($user, $validated['password'])) {
                Auth::login($user);

                return $this->completeAuthenticatedLogin($request, $validated['role']);
            }
        } catch (Throwable $exception) {
            if (! config('auth.demo_login')) {
                throw $exception;
            }
        }

        if (config('auth.demo_login')) {
            $request->session()->regenerate();
            $request->session()->put([
                'demo_authenticated' => true,
                'demo_email' => $validated['email'],
                'active_role' => $validated['role'],
            ]);

            return redirect()->intended(route('dashboard'));
        }

        return back()->withErrors([
            'email' => 'Email ou mot de passe incorrect, ou utilisateur introuvable dans Oracle.',
        ])->onlyInput(['email', 'role']);
    }

    public function resetPassword(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'tel' => ['required', 'string', 'max:20'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ]);

        $user = User::query()
            ->whereRaw('LOWER(email) = ?', [strtolower($validated['email'])])
            ->first();

        if (! $user || trim((string) $user->tel) !== trim($validated['tel'])) {
            return back()
                ->withErrors([
                    'email' => 'Email ou telephone incorrect.',
                ])
                ->withInput($request->only('email', 'tel'));
        }

        $user->forceFill([
            'password' => Hash::make($validated['password']),
        ])->save();

        return redirect()
            ->route('login')
            ->with('status', 'Mot de passe reinitialise. Vous pouvez vous connecter.');
    }

    private function passwordMatches(User $user, string $password): bool
    {
        if (Hash::check($password, $user->password)) {
            return true;
        }

        if (hash_equals((string) $user->password, $password)) {
            $user->forceFill([
                'password' => Hash::make($password),
            ])->save();

            return true;
        }

        return false;
    }

    private function completeAuthenticatedLogin(Request $request, string $selectedRole)
    {
        $request->session()->regenerate();

        $user = $request->user();
        $databaseRole = $user?->role ? User::normalizeRole($user->role) : null;
        $selectedRole = User::normalizeRole($selectedRole);

        if ($databaseRole && $databaseRole !== $selectedRole) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()
                ->withErrors(['role' => 'Le rôle sélectionné ne correspond pas au rôle de cet utilisateur.'])
                ->withInput($request->only('email', 'role'));
        }

        $request->session()->forget(['demo_authenticated', 'demo_email']);
        $request->session()->put('active_role', $databaseRole ?: $selectedRole);

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
