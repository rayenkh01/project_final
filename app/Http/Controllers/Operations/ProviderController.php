<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\VasServiceProvider;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ProviderController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));

        $query = VasServiceProvider::query()
            ->withCount('services')
            ->orderBy('id');

        if ($search !== '') {
            $like = '%'.strtolower($search).'%';

            $query->where(function ($builder) use ($like): void {
                $builder
                    ->whereRaw('LOWER(provider_name) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(nationnalite) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(id_fiscale) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(adresse) LIKE ?', [$like]);
            });
        }

        return view('operations.providers.index', [
            'activeRole' => User::ROLE_OPERATIONAL,
            'roleLabel' => User::roleLabel(User::ROLE_OPERATIONAL),
            'providers' => $query->paginate(10)->withQueryString(),
            'search' => $search,
            'stats' => [
                'total' => VasServiceProvider::query()->count(),
                'with_services' => VasServiceProvider::query()->has('services')->count(),
                'without_services' => VasServiceProvider::query()->doesntHave('services')->count(),
            ],
        ]);
    }

    public function create(): View
    {
        return view('operations.providers.create', [
            'activeRole' => User::ROLE_OPERATIONAL,
            'roleLabel' => User::roleLabel(User::ROLE_OPERATIONAL),
            'provider' => new VasServiceProvider(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedData($request);
        $this->ensureProviderNameIsUnique($data['provider_name']);

        DB::table('service_provider')->insert([
            'id' => $this->nextProviderId(),
            'provider_name' => $data['provider_name'],
            'nationnalite' => $data['nationnalite'] ?: null,
            'id_fiscale' => $data['id_fiscale'] ?: null,
            'adresse' => $data['adresse'] ?: null,
        ]);

        return redirect()
            ->route('operations.providers.index')
            ->with('status', 'Fournisseur ajoute avec succes.');
    }

    public function edit(VasServiceProvider $provider): View
    {
        return view('operations.providers.edit', [
            'activeRole' => User::ROLE_OPERATIONAL,
            'roleLabel' => User::roleLabel(User::ROLE_OPERATIONAL),
            'provider' => $provider,
        ]);
    }

    public function update(Request $request, VasServiceProvider $provider): RedirectResponse
    {
        $data = $this->validatedData($request);
        $this->ensureProviderNameIsUnique($data['provider_name'], (int) $provider->id);

        $provider->forceFill([
            'provider_name' => $data['provider_name'],
            'nationnalite' => $data['nationnalite'] ?: null,
            'id_fiscale' => $data['id_fiscale'] ?: null,
            'adresse' => $data['adresse'] ?: null,
        ])->save();

        return redirect()
            ->route('operations.providers.index')
            ->with('status', 'Fournisseur modifie avec succes.');
    }

    public function destroy(VasServiceProvider $provider): RedirectResponse
    {
        if ($provider->services()->exists()) {
            return back()->withErrors([
                'delete' => 'Impossible de supprimer ce fournisseur car il est rattache a des services.',
            ]);
        }

        try {
            $provider->delete();
        } catch (QueryException) {
            return back()->withErrors([
                'delete' => 'Suppression impossible: ce fournisseur est utilise par une autre table.',
            ]);
        }

        return redirect()
            ->route('operations.providers.index')
            ->with('status', 'Fournisseur supprime avec succes.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedData(Request $request): array
    {
        return $request->validate([
            'provider_name' => ['required', 'string', 'max:100'],
            'nationnalite' => ['nullable', 'string', 'max:100'],
            'id_fiscale' => ['nullable', 'string', 'max:50'],
            'adresse' => ['nullable', 'string', 'max:200'],
        ]);
    }

    private function ensureProviderNameIsUnique(string $providerName, ?int $ignoredProviderId = null): void
    {
        $query = VasServiceProvider::query()
            ->whereRaw('LOWER(provider_name) = ?', [strtolower($providerName)]);

        if ($ignoredProviderId) {
            $query->where('id', '<>', $ignoredProviderId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'provider_name' => 'Ce fournisseur existe deja.',
            ]);
        }
    }

    private function nextProviderId(): int
    {
        return ((int) VasServiceProvider::query()->max('id')) + 1;
    }
}
