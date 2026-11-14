<?php

namespace App\Livewire\Warehouses;

use App\Domain\Warehouse\Models\Warehouse;
use App\Domain\Warehouse\Models\WarehouseBin;
use App\Domain\Warehouse\Models\WarehouseLocation;
use App\Domain\Warehouse\Services\WarehouseService;
use App\DTOs\Warehouse\AccessGrantData;
use App\DTOs\Warehouse\BinData;
use App\DTOs\Warehouse\LocationData;
use App\Enums\WarehouseAccessLevel;
use App\Enums\WarehouseLocationType;
use App\Livewire\Concerns\HandlesDomainActions;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Warehouse')]
class Show extends Component
{
    use HandlesDomainActions;

    public Warehouse $warehouse;

    public bool $showLocationForm = false;

    public ?int $editingLocationId = null;

    /** @var array<string, mixed> */
    public array $locationForm = [];

    public bool $showBinForm = false;

    public ?int $editingBinId = null;

    public ?int $binLocationId = null;

    public string $binLocationName = '';

    /** @var array<string, mixed> */
    public array $binForm = [];

    public bool $showAccessForm = false;

    /** @var array<string, mixed> */
    public array $accessForm = [];

    public function mount(Warehouse $warehouse): void
    {
        $this->warehouse = $warehouse;
        $this->authorize('view', $this->warehouse);

        $this->resetLocationForm();
        $this->resetBinForm();
        $this->resetAccessForm();
    }

    public function resetLocationForm(): void
    {
        $this->editingLocationId = null;
        $this->locationForm = [
            'code' => '',
            'name' => '',
            'type' => WarehouseLocationType::Storage->value,
            'description' => '',
            'is_active' => true,
        ];
        $this->resetValidation();
    }

    public function openCreateLocation(): void
    {
        $this->authorize('create', WarehouseLocation::class);
        $this->assertManageAccess();

        $this->resetLocationForm();
        $this->showLocationForm = true;
    }

    public function openEditLocation(int $locationId): void
    {
        $location = $this->warehouse->locations()->findOrFail($locationId);
        $this->authorize('update', $location);

        $this->editingLocationId = $location->id;
        $this->locationForm = [
            'code' => $location->code,
            'name' => $location->name,
            'type' => $location->type->value,
            'description' => (string) $location->description,
            'is_active' => $location->is_active,
        ];
        $this->resetValidation();
        $this->showLocationForm = true;
    }

    public function saveLocation(WarehouseService $service): void
    {
        $location = $this->editingLocationId !== null
            ? $this->warehouse->locations()->findOrFail($this->editingLocationId)
            : null;

        $location !== null
            ? $this->authorize('update', $location)
            : $this->authorize('create', WarehouseLocation::class);

        $this->assertManageAccess();

        $this->validate([
            'locationForm.code' => [
                'required', 'string', 'max:30',
                Rule::unique('warehouse_locations', 'code')
                    ->where('warehouse_id', $this->warehouse->id)
                    ->ignore($location?->id),
            ],
            'locationForm.name' => ['required', 'string', 'max:150'],
            'locationForm.type' => ['required', Rule::enum(WarehouseLocationType::class)],
            'locationForm.description' => ['nullable', 'string', 'max:1000'],
            'locationForm.is_active' => ['boolean'],
        ]);

        $data = LocationData::fromArray($this->locationForm);

        $saved = $this->runDomainAction(
            fn () => $location !== null
                ? $service->updateLocation($location, $data)
                : $service->createLocation($this->warehouse, $data),
            $location !== null ? 'Location updated.' : 'Location created.',
        );

        if ($saved) {
            $this->showLocationForm = false;
            $this->resetLocationForm();
        }
    }

    public function deleteLocation(int $locationId, WarehouseService $service): void
    {
        $location = $this->warehouse->locations()->findOrFail($locationId);
        $this->authorize('delete', $location);

        $this->runDomainAction(fn () => $service->deleteLocation($location), 'Location deleted.');
    }

    public function resetBinForm(): void
    {
        $this->editingBinId = null;
        $this->binLocationId = null;
        $this->binLocationName = '';
        $this->binForm = [
            'code' => '',
            'name' => '',
            'max_capacity' => '',
            'is_active' => true,
        ];
        $this->resetValidation();
    }

    public function openCreateBin(int $locationId): void
    {
        $this->authorize('create', WarehouseBin::class);
        $this->assertManageAccess();

        $location = $this->warehouse->locations()->findOrFail($locationId);

        $this->resetBinForm();
        $this->binLocationId = $location->id;
        $this->binLocationName = $location->name;
        $this->showBinForm = true;
    }

    public function openEditBin(int $binId): void
    {
        $bin = WarehouseBin::query()
            ->whereHas('location', fn (Builder $query) => $query->where('warehouse_id', $this->warehouse->id))
            ->findOrFail($binId);

        $this->authorize('update', $bin);

        $this->editingBinId = $bin->id;
        $this->binLocationId = $bin->warehouse_location_id;
        $this->binLocationName = (string) $bin->location?->name;
        $this->binForm = [
            'code' => $bin->code,
            'name' => (string) $bin->name,
            'max_capacity' => $bin->max_capacity !== null ? (string) $bin->max_capacity : '',
            'is_active' => $bin->is_active,
        ];
        $this->resetValidation();
        $this->showBinForm = true;
    }

    public function saveBin(WarehouseService $service): void
    {
        $location = $this->warehouse->locations()->findOrFail($this->binLocationId);

        $bin = $this->editingBinId !== null
            ? WarehouseBin::query()->where('warehouse_location_id', $location->id)->findOrFail($this->editingBinId)
            : null;

        $bin !== null
            ? $this->authorize('update', $bin)
            : $this->authorize('create', WarehouseBin::class);

        $this->assertManageAccess();

        $this->validate([
            'binForm.code' => [
                'required', 'string', 'max:30',
                Rule::unique('warehouse_bins', 'code')
                    ->where('warehouse_location_id', $location->id)
                    ->ignore($bin?->id),
            ],
            'binForm.name' => ['nullable', 'string', 'max:150'],
            'binForm.max_capacity' => ['nullable', 'numeric', 'min:0'],
            'binForm.is_active' => ['boolean'],
        ]);

        $payload = $this->binForm;
        $payload['max_capacity'] = $this->binForm['max_capacity'] !== '' ? $this->binForm['max_capacity'] : null;

        $data = BinData::fromArray($payload);

        $saved = $this->runDomainAction(
            fn () => $bin !== null
                ? $service->updateBin($bin, $data)
                : $service->createBin($location, $data),
            $bin !== null ? 'Bin updated.' : 'Bin created.',
        );

        if ($saved) {
            $this->showBinForm = false;
            $this->resetBinForm();
        }
    }

    public function deleteBin(int $binId, WarehouseService $service): void
    {
        $bin = WarehouseBin::query()
            ->whereHas('location', fn (Builder $query) => $query->where('warehouse_id', $this->warehouse->id))
            ->findOrFail($binId);

        $this->authorize('delete', $bin);

        $this->runDomainAction(fn () => $service->deleteBin($bin), 'Bin deleted.');
    }

    public function resetAccessForm(): void
    {
        $this->accessForm = [
            'user_id' => '',
            'access_level' => WarehouseAccessLevel::Operate->value,
            'is_primary' => false,
        ];
        $this->resetValidation();
    }

    public function openAccessForm(): void
    {
        $this->authorize('manageAccess', $this->warehouse);
        $this->resetAccessForm();
        $this->showAccessForm = true;
    }

    public function grantAccess(WarehouseService $service): void
    {
        $this->authorize('manageAccess', $this->warehouse);

        $this->validate([
            'accessForm.user_id' => ['required', 'integer', Rule::exists('users', 'id')],
            'accessForm.access_level' => ['required', Rule::enum(WarehouseAccessLevel::class)],
            'accessForm.is_primary' => ['boolean'],
        ]);

        $data = AccessGrantData::fromArray($this->accessForm);

        if ($this->runDomainAction(
            fn () => $service->grantAccess($this->warehouse, $data, auth()->user()),
            'Warehouse access granted.',
        )) {
            $this->showAccessForm = false;
            $this->resetAccessForm();
        }
    }

    public function revokeAccess(int $grantId, WarehouseService $service): void
    {
        $this->authorize('manageAccess', $this->warehouse);

        $grant = $this->warehouse->accessGrants()->findOrFail($grantId);
        $user = $grant->user;

        if ($user === null) {
            $this->error('The user for this grant no longer exists.');

            return;
        }

        $this->runDomainAction(
            fn () => $service->revokeAccess($this->warehouse, $user, auth()->user()),
            'Warehouse access revoked.',
        );
    }

    public function render()
    {
        $this->warehouse->refresh();

        $locations = $this->warehouse->locations()
            ->with(['bins' => fn ($query) => $query->orderBy('code')])
            ->orderBy('code')
            ->get();

        $grants = $this->warehouse->accessGrants()
            ->with(['user', 'grantedBy'])
            ->whereNull('revoked_at')
            ->latest('granted_at')
            ->get();

        $grantedUserIds = $grants->pluck('user_id')->all();

        return view('livewire.warehouses.show', [
            'locations' => $locations,
            'grants' => $grants,
            'locationTypeOptions' => WarehouseLocationType::cases(),
            'accessLevelOptions' => WarehouseAccessLevel::cases(),
            'users' => User::query()
                ->where('status', 'active')
                ->when($grantedUserIds !== [], fn (Builder $query) => $query->whereNotIn('id', $grantedUserIds))
                ->orderBy('name')
                ->get(['id', 'name', 'email']),
        ]);
    }

    private function assertManageAccess(): void
    {
        abort_unless(
            auth()->user()->hasWarehouseAccess($this->warehouse, WarehouseAccessLevel::Manage),
            403,
            'You need Manage access to change this warehouse structure.',
        );
    }
}
