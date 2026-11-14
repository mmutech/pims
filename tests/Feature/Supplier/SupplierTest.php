<?php

use App\Domain\Supplier\Models\Supplier;
use App\Domain\Supplier\Services\SupplierService;
use App\DTOs\Supplier\SupplierData;
use App\Enums\SupplierStatus;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Auth\Access\AuthorizationException;

beforeEach(function () {
    $this->seed([PermissionSeeder::class, RoleSeeder::class, SettingsSeeder::class]);
});

it('registers a supplier in pending status', function () {
    $actor = User::factory()->withRole('Procurement Officer')->create();

    $data = SupplierData::fromArray([
        'code' => 'sup-9001',
        'name' => 'Grid Components Limited',
        'email' => 'sales@gridcomponents.test',
    ]);

    $supplier = app(SupplierService::class)->create($data, $actor);

    expect($supplier->code)->toBe('SUP-9001')
        ->and($supplier->status)->toBe(SupplierStatus::Pending)
        ->and($supplier->created_by)->toBe($actor->id)
        ->and($supplier->is_approved)->toBeFalse();
});

it('approves a supplier registered by another user', function () {
    $officer = User::factory()->withRole('Procurement Officer')->create();
    $manager = User::factory()->withRole('Procurement Manager')->create();
    $supplier = Supplier::factory()->create(['created_by' => $officer->id]);

    $approved = app(SupplierService::class)->approve($supplier, $manager);

    expect($approved->status)->toBe(SupplierStatus::Active)
        ->and($approved->is_approved)->toBeTrue()
        ->and($approved->approved_by)->toBe($manager->id)
        ->and($approved->approved_at)->not->toBeNull();
});

it('enforces segregation of duties on supplier approval', function () {
    $manager = User::factory()->withRole('Procurement Manager')->create();
    $supplier = Supplier::factory()->create(['created_by' => $manager->id]);

    expect(fn () => app(SupplierService::class)->approve($supplier, $manager))
        ->toThrow(DomainException::class, 'Segregation of duties: you cannot approve a supplier you registered.');
});

it('requires the approve permission to approve a supplier', function () {
    $officer = User::factory()->withRole('Procurement Officer')->create();
    $supplier = Supplier::factory()->create();

    expect(fn () => app(SupplierService::class)->approve($supplier, $officer))
        ->toThrow(AuthorizationException::class, 'You are not permitted to approve suppliers.');
});

it('requires a reason when suspending a supplier', function () {
    $manager = User::factory()->withRole('Procurement Manager')->create();
    $supplier = Supplier::factory()->approved()->create();

    expect(fn () => app(SupplierService::class)->changeStatus($supplier, SupplierStatus::Suspended, $manager))
        ->toThrow(DomainException::class, 'A reason is required when suspending or blacklisting a supplier.');
});

it('records the suspension reason in the supplier notes', function () {
    $manager = User::factory()->withRole('Procurement Manager')->create();
    $supplier = Supplier::factory()->approved()->create();

    $suspended = app(SupplierService::class)
        ->changeStatus($supplier, SupplierStatus::Suspended, $manager, 'Failed quality audit');

    expect($suspended->status)->toBe(SupplierStatus::Suspended)
        ->and($suspended->notes)->toContain('Failed quality audit');
});

it('only allows a super administrator to blacklist a supplier', function () {
    $manager = User::factory()->withRole('Procurement Manager')->create();
    $superAdmin = User::factory()->superAdmin()->create();
    $supplier = Supplier::factory()->approved()->create();

    expect(fn () => app(SupplierService::class)
        ->changeStatus($supplier, SupplierStatus::Blacklisted, $manager, 'Fraud'))
        ->toThrow(AuthorizationException::class, 'Only a Super Administrator can blacklist a supplier.');

    $blacklisted = app(SupplierService::class)
        ->changeStatus($supplier, SupplierStatus::Blacklisted, $superAdmin, 'Fraud');

    expect($blacklisted->status)->toBe(SupplierStatus::Blacklisted);
});

it('prevents activating a supplier that has not been approved', function () {
    $manager = User::factory()->withRole('Procurement Manager')->create();
    $supplier = Supplier::factory()->create();

    expect(fn () => app(SupplierService::class)
        ->changeStatus($supplier, SupplierStatus::Active, $manager))
        ->toThrow(DomainException::class, 'A supplier must be approved before it can be active.');
});

it('prevents changing a supplier code once created', function () {
    $manager = User::factory()->withRole('Procurement Manager')->create();
    $supplier = Supplier::factory()->create();

    $data = SupplierData::fromArray([
        'code' => 'SUP-CHANGED',
        'name' => $supplier->name,
        'status' => $supplier->status->value,
    ]);

    expect(fn () => app(SupplierService::class)->update($supplier, $data, $manager))
        ->toThrow(DomainException::class, 'The supplier code is immutable once created.');
});

it('computes supplier evaluation scores with fixed precision', function () {
    $manager = User::factory()->withRole('Procurement Manager')->create();
    $supplier = Supplier::factory()->approved()->create();

    $evaluation = app(SupplierService::class)->addEvaluation($supplier, [
        'quality_score' => '4.5',
        'delivery_score' => '3.5',
        'pricing_score' => '4.0',
        'service_score' => '4.0',
    ], $manager, 'Reliable supplier');

    expect($evaluation->overall_score)->toBe('4.00')
        ->and($supplier->fresh()->rating)->toBe('4.00');
});

it('rejects supplier evaluation scores outside the allowed range', function () {
    $manager = User::factory()->withRole('Procurement Manager')->create();
    $supplier = Supplier::factory()->approved()->create();

    expect(fn () => app(SupplierService::class)->addEvaluation($supplier, [
        'quality_score' => '6',
        'delivery_score' => '3.5',
        'pricing_score' => '4.0',
        'service_score' => '4.0',
    ], $manager))
        ->toThrow(DomainException::class, 'Supplier evaluation scores must be between 0 and 5.');
});
