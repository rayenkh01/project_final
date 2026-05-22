<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
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

            if ($user && $user->hasPendingInvitation()) {
                return back()
                    ->withErrors([
                        'email' => 'Ce compte est en attente d activation. Veuillez utiliser le lien recu par email.',
                    ])
                    ->withInput($request->only('email', 'role'));
            }

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

    public function sendPasswordResetLink(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $user = User::query()
            ->whereRaw('LOWER(email) = ?', [strtolower($validated['email'])])
            ->first();

        if ($user && ! $user->hasPendingInvitation()) {
            $token = Str::random(64);

            DB::table('password_reset_tokens')
                ->whereRaw('LOWER(email) = ?', [strtolower($user->email)])
                ->delete();

            DB::table('password_reset_tokens')->insert([
                'email' => strtolower($user->email),
                'token' => Hash::make($token),
                'created_at' => now(),
            ]);

            try {
                Mail::send('emails.auth.password-reset', [
                    'email' => strtolower($user->email),
                    'resetUrl' => route('password.reset.custom', [
                        'token' => $token,
                        'email' => strtolower($user->email),
                    ]),
                    'expiresInMinutes' => 60,
                ], function ($message) use ($user): void {
                    $message
                        ->to(strtolower($user->email))
                        ->subject('Reinitialisation du mot de passe VAS CDR');
                });
            } catch (Throwable $e) {
                Log::warning('Password reset email could not be sent.', [
                    'email' => strtolower($user->email),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return back()->with('status', 'Si un compte actif existe avec cet email, un lien de reinitialisation sera envoye.');
    }

    public function showResetPassword(string $token, Request $request)
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
        ]);
    }

    public function resetPassword(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'token' => ['required', 'string'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ]);

        $record = DB::table('password_reset_tokens')
            ->whereRaw('LOWER(email) = ?', [strtolower($validated['email'])])
            ->first();

        if (! $record || ! Hash::check($validated['token'], (string) $record->token) || now()->diffInMinutes($record->created_at) > 60) {
            return back()
                ->withErrors(['email' => 'Lien de reinitialisation invalide ou expire.'])
                ->withInput($request->only('email'));
        }

        $user = User::query()
            ->whereRaw('LOWER(email) = ?', [strtolower($validated['email'])])
            ->first();

        if (! $user || $user->hasPendingInvitation()) {
            return back()
                ->withErrors(['email' => 'Ce compte ne peut pas utiliser cette reinitialisation.'])
                ->withInput($request->only('email'));
        }

        $user->forceFill([
            'password' => Hash::make($validated['password']),
        ])->save();

        DB::table('password_reset_tokens')
            ->whereRaw('LOWER(email) = ?', [strtolower($validated['email'])])
            ->delete();

        return redirect()
            ->route('login')
            ->with('status', 'Mot de passe reinitialise. Vous pouvez vous connecter.');
    }

    public function showInvitation(string $token)
    {
        $user = $this->findUserByInvitationToken($token);

        return view('auth.accept-invitation', [
            'token' => $token,
            'user' => $user,
            'isValid' => (bool) $user,
        ]);
    }

    public function acceptInvitation(Request $request)
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ]);

        $user = $this->findUserByInvitationToken($validated['token']);

        if (! $user) {
            return back()->withErrors([
                'token' => 'Lien d activation invalide ou expire.',
            ]);
        }

        $user->forceFill([
            'password' => Hash::make($validated['password']),
            'email_verified_at' => now(),
            'invitation_token_hash' => null,
            'invitation_expires_at' => null,
        ])->save();

        return redirect()
            ->route('login')
            ->with('status', 'Compte active. Vous pouvez maintenant vous connecter.');
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

    private function findUserByInvitationToken(string $token): ?User
    {
        return User::query()
            ->where('invitation_token_hash', hash('sha256', $token))
            ->where('invitation_expires_at', '>=', now())
            ->first();
    }

    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
