<?php

namespace App\Domain\Warehouse\Services;

use App\Domain\Warehouse\Models\UserWarehouseAccess;
use App\Domain\Warehouse\Models\Warehouse;
use App\Domain\Warehouse\Models\WarehouseBin;
use App\Domain\Warehouse\Models\WarehouseLocation;
use App\DTOs\Warehouse\AccessGrantData;
use App\DTOs\Warehouse\BinData;
use App\DTOs\Warehouse\LocationData;
use App\DTOs\Warehouse\WarehouseData;
use App\Enums\WarehouseAccessLevel;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

class WarehouseService
{
    public function create(WarehouseData $data, User $actor): Warehouse
    {
        return DB::transaction(function () use ($data, $actor): Warehouse {
            $warehouse = Warehouse::create($data->toArray());

            if ($data->managerId !== null) {
                $this->grantAccess($warehouse, new AccessGrantData($data->managerId, WarehouseAccessLevel::Manage, true), $actor);
            }

            return $warehouse;
        });
    }

    public function update(Warehouse $warehouse, WarehouseData $data, User $actor): Warehouse
    {
        if ($warehouse->code !== $data->code) {
            throw new DomainException('The warehouse code is immutable once created.');
        }

        if (! $data->isActive && $warehouse->hasActiveLocations()) {
            throw new DomainException('Warehouse cannot be deactivated while active locations exist.');
        }

        $warehouse->update($data->toArray());

        if ($data->managerId !== null && ! $warehouse->managerHasAccess()) {
            $this->grantAccess($warehouse, new AccessGrantData($data->managerId, WarehouseAccessLevel::Manage, true), $actor);
        }

        return $warehouse->refresh();
    }

    public function delete(Warehouse $warehouse): void
    {
        if ($warehouse->locations()->exists()) {
            throw new DomainException('Warehouse cannot be deleted while locations exist.');
        }

        if ($warehouse->accessGrants()->exists()) {
            throw new DomainException('Warehouse cannot be deleted while access grants exist.');
        }

        $warehouse->delete();
    }

    public function createLocation(Warehouse $warehouse, LocationData $data): WarehouseLocation
    {
        return $warehouse->locations()->create($data->toArray());
    }

    public function updateLocation(WarehouseLocation $location, LocationData $data): WarehouseLocation
    {
        if ($location->code !== $data->code) {
            throw new DomainException('The location code is immutable once created.');
        }

        if (! $data->isActive && $location->hasActiveBins()) {
            throw new DomainException('Location cannot be deactivated while active bins exist.');
        }

        $location->update($data->toArray());

        return $location->refresh();
    }

    public function deleteLocation(WarehouseLocation $location): void
    {
        if ($location->bins()->exists()) {
            throw new DomainException('Location cannot be deleted while bins exist.');
        }

        $location->delete();
    }

    public function createBin(WarehouseLocation $location, BinData $data): WarehouseBin
    {
        return $location->bins()->create($data->toArray());
    }

    public function updateBin(WarehouseBin $bin, BinData $data): WarehouseBin
    {
        if ($bin->code !== $data->code) {
            throw new DomainException('The bin code is immutable once created.');
        }

        $bin->update($data->toArray());

        return $bin->refresh();
    }

    public function deleteBin(WarehouseBin $bin): void
    {
        $bin->delete();
    }

    public function grantAccess(Warehouse $warehouse, AccessGrantData $data, User $actor): UserWarehouseAccess
    {
        $user = User::findOrFail($data->userId);

        if (! $user->isActive()) {
            throw new DomainException('Warehouse access cannot be granted to an inactive user.');
        }

        $grant = $warehouse->accessGrants()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'access_level' => $data->accessLevel,
                'is_primary' => $data->isPrimary,
                'granted_by' => $actor->id,
                'granted_at' => now(),
                'revoked_at' => null,
            ],
        );

        return $grant;
    }

    public function revokeAccess(Warehouse $warehouse, User $user, User $actor): void
    {
        if ($warehouse->manager_id === $user->id) {
            throw new DomainException('Revoke the manager assignment before removing warehouse access.');
        }

        $warehouse->accessGrants()
            ->where('user_id', $user->id)
            ->whereNull('revoked_at')
            ->update([
                'revoked_at' => now(),
                'granted_by' => $actor->id,
            ]);
    }
}
