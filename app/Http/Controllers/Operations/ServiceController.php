<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\User;
use App\Models\VasServiceProvider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ServiceController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));

        $query = Service::query()
            ->with('provider')
            ->orderBy('id');

        if ($search !== '') {
            $like = '%'.strtolower($search).'%';

            $query->where(function ($builder) use ($like): void {
                $builder
                    ->whereRaw('LOWER(service_name) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(short_code) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(keyword) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(type) LIKE ?', [$like])
                    ->orWhereHas('provider', function ($providerQuery) use ($like): void {
                        $providerQuery->whereRaw('LOWER(provider_name) LIKE ?', [$like]);
                    });
            });
        }

        return view('operations.services.index', [
            'activeRole' => User::ROLE_OPERATIONAL,
            'roleLabel' => User::roleLabel(User::ROLE_OPERATIONAL),
            'services' => $query->paginate(10)->withQueryString(),
            'search' => $search,
            'stats' => [
                'total' => Service::query()->count(),
                'providers' => VasServiceProvider::query()->count(),
                'priced' => Service::query()->whereNotNull('price')->count(),
                'keywords' => Service::query()->whereNotNull('keyword')->count(),
            ],
        ]);
    }

    public function create(): View
    {
        return view('operations.services.create', [
            'activeRole' => User::ROLE_OPERATIONAL,
            'roleLabel' => User::roleLabel(User::ROLE_OPERATIONAL),
            'service' => new Service(),
            'providers' => $this->providers(),
            'types' => $this->types(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedData($request);
        $this->ensureShortCodeKeywordIsUnique($data['short_code'], $data['keyword']);

        DB::table('services')->insert([
            'id' => $this->nextServiceId(),
            'service_name' => $data['service_name'],
            'short_code' => $data['short_code'],
            'keyword' => $data['keyword'] ?: null,
            'type' => $data['type'] ?: null,
            'price' => $data['price'] ?? null,
            'provider_id' => $data['provider_id'] ?: null,
        ]);

        return redirect()
            ->route('operations.services.index')
            ->with('status', 'Service ajoute avec succes.');
    }

    public function edit(Service $service): View
    {
        return view('operations.services.edit', [
            'activeRole' => User::ROLE_OPERATIONAL,
            'roleLabel' => User::roleLabel(User::ROLE_OPERATIONAL),
            'service' => $service,
            'providers' => $this->providers(),
            'types' => $this->types(),
        ]);
    }

    public function update(Request $request, Service $service): RedirectResponse
    {
        $data = $this->validatedData($request);
        $this->ensureShortCodeKeywordIsUnique($data['short_code'], $data['keyword'], (int) $service->id);

        $service->forceFill([
            'service_name' => $data['service_name'],
            'short_code' => $data['short_code'],
            'keyword' => $data['keyword'] ?: null,
            'type' => $data['type'] ?: null,
            'price' => $data['price'] ?? null,
            'provider_id' => $data['provider_id'] ?: null,
        ])->save();

        return redirect()
            ->route('operations.services.index')
            ->with('status', 'Service modifie avec succes.');
    }

    public function destroy(Service $service): RedirectResponse
    {
        $service->delete();

        return redirect()
            ->route('operations.services.index')
            ->with('status', 'Service supprime avec succes.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedData(Request $request): array
    {
        return $request->validate([
            'service_name' => ['required', 'string', 'max:100'],
            'short_code' => ['required', 'string', 'max:20'],
            'keyword' => ['nullable', 'string', 'max:50'],
            'type' => ['nullable', 'string', 'max:50'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'provider_id' => [
                'nullable',
                'integer',
                Rule::exists('service_provider', 'id'),
            ],
        ]);
    }

    private function ensureShortCodeKeywordIsUnique(string $shortCode, ?string $keyword, ?int $ignoredServiceId = null): void
    {
        $query = Service::query()
            ->whereRaw('LOWER(short_code) = ?', [strtolower($shortCode)]);

        if ($keyword) {
            $query->whereRaw('LOWER(keyword) = ?', [strtolower($keyword)]);
        } else {
            $query->whereNull('keyword');
        }

        if ($ignoredServiceId) {
            $query->where('id', '<>', $ignoredServiceId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'short_code' => 'Un service avec ce short code et ce keyword existe deja.',
            ]);
        }
    }

    private function nextServiceId(): int
    {
        return ((int) Service::query()->max('id')) + 1;
    }

    private function providers()
    {
        return VasServiceProvider::query()
            ->orderBy('provider_name')
            ->get(['id', 'provider_name']);
    }

    /**
     * @return array<int, string>
     */
    private function types(): array
    {
        return [
            'SMS+',
            'Subscription',
            'One Shot',
            'Information',
            'Entertainment',
            'Gaming',
        ];
    }
}
