<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));

        $query = User::query()->orderBy('id');

        if ($search !== '') {
            $like = '%'.strtolower($search).'%';

            $query->where(function ($builder) use ($like): void {
                $builder
                    ->whereRaw('LOWER(email) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(direction) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(role) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(tel) LIKE ?', [$like]);
            });
        }

        return view('admin.users.index', [
            'activeRole' => User::ROLE_ADMIN,
            'roleLabel' => User::roleLabel(User::ROLE_ADMIN),
            'users' => $query->paginate(10)->withQueryString(),
            'roles' => User::oracleRoleOptions(),
            'search' => $search,
            'stats' => [
                'total' => User::query()->count(),
                'admin' => User::query()->where('role', User::ORACLE_ROLE_ADMIN)->count(),
                'business' => User::query()->where('role', User::ORACLE_ROLE_BUSINESS)->count(),
                'operation' => User::query()->where('role', User::ORACLE_ROLE_OPERATION)->count(),
            ],
        ]);
    }

    public function create(): View
    {
        return view('admin.users.create', [
            'activeRole' => User::ROLE_ADMIN,
            'roleLabel' => User::roleLabel(User::ROLE_ADMIN),
            'roles' => User::oracleRoleOptions(),
            'user' => new User(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedData($request);
        $this->ensureEmailIsUnique($data['email']);

        DB::table('users')->insert([
            'id' => $this->nextUserId(),
            'email' => strtolower($data['email']),
            'password' => Hash::make($data['password']),
            'direction' => $data['direction'] ?: null,
            'role' => $data['role'],
            'tel' => $data['tel'] ?: null,
            'created_at' => DB::raw('SYSDATE'),
        ]);

        return redirect()
            ->route('admin.users.index')
            ->with('status', 'Utilisateur ajoute avec succes.');
    }

    public function edit(User $user): View
    {
        return view('admin.users.edit', [
            'activeRole' => User::ROLE_ADMIN,
            'roleLabel' => User::roleLabel(User::ROLE_ADMIN),
            'roles' => User::oracleRoleOptions(),
            'user' => $user,
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $this->validatedData($request, $user);
        $this->ensureEmailIsUnique($data['email'], (int) $user->id);

        $payload = [
            'email' => strtolower($data['email']),
            'direction' => $data['direction'] ?: null,
            'role' => $data['role'],
            'tel' => $data['tel'] ?: null,
        ];

        if (! empty($data['password'])) {
            $payload['password'] = Hash::make($data['password']);
        }

        $user->forceFill($payload)->save();

        return redirect()
            ->route('admin.users.index')
            ->with('status', 'Utilisateur modifie avec succes.');
    }

    public function destroy(User $user): RedirectResponse
    {
        if (auth()->id() === (int) $user->id) {
            return back()->withErrors([
                'delete' => 'Vous ne pouvez pas supprimer votre propre compte connecte.',
            ]);
        }

        $user->delete();

        return redirect()
            ->route('admin.users.index')
            ->with('status', 'Utilisateur supprime avec succes.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedData(Request $request, ?User $user = null): array
    {
        return $request->validate([
            'email' => ['required', 'email', 'max:150'],
            'password' => [$user ? 'nullable' : 'required', 'string', 'min:6', 'max:120'],
            'direction' => ['nullable', 'string', 'max:100'],
            'role' => ['required', Rule::in(User::oracleRoles())],
            'tel' => ['nullable', 'string', 'max:20'],
        ]);
    }

    private function ensureEmailIsUnique(string $email, ?int $ignoredUserId = null): void
    {
        $query = User::query()
            ->whereRaw('LOWER(email) = ?', [strtolower($email)]);

        if ($ignoredUserId) {
            $query->where('id', '<>', $ignoredUserId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'email' => 'Cet email existe deja.',
            ]);
        }
    }

    private function nextUserId(): int
    {
        return ((int) User::query()->max('id')) + 1;
    }
}
