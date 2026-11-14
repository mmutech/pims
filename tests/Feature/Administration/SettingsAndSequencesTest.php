<?php

use App\Domain\Administration\Models\SystemSetting;
use App\Domain\Administration\Services\DocumentSequenceService;
use App\Domain\Administration\Services\SystemSettingService;
use App\Enums\DocumentType;
use App\Livewire\Administration\Sequences\Index as SequencesIndex;
use App\Livewire\Administration\Settings\Index as SettingsIndex;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed([PermissionSeeder::class, RoleSeeder::class]);
});

it('allocates sequential document numbers with the configured format', function () {
    $service = app(DocumentSequenceService::class);

    $first = $service->nextNumber(DocumentType::PurchaseOrder, 2026);
    $second = $service->nextNumber(DocumentType::PurchaseOrder, 2026);

    expect($first)->toBe('PO/2026/00001')
        ->and($second)->toBe('PO/2026/00002');
});

it('applies padding changes to document numbers', function () {
    $service = app(DocumentSequenceService::class);
    $sequence = $service->create(DocumentType::StockIssue, 'SIV', 2026, 7, 3, 'yearly');

    expect($sequence->preview())->toBe('SIV/2026/007');
});

it('rejects invalid document sequence settings', function () {
    $service = app(DocumentSequenceService::class);
    $sequence = $service->create(DocumentType::StockTransfer, 'TRF', 2026);

    expect(fn () => $service->update($sequence, 1, 0, 'yearly'))
        ->toThrow(DomainException::class, 'Padding must be between 1 and 10.');

    expect(fn () => $service->update($sequence, 0, 5, 'yearly'))
        ->toThrow(DomainException::class, 'The next number must be at least 1.');

    expect(fn () => $service->update($sequence, 1, 5, 'monthly'))
        ->toThrow(DomainException::class, 'Reset policy must be either yearly or never.');
});

it('stores encrypted settings encrypted and returns plaintext through the service', function () {
    $service = app(SystemSettingService::class);
    $service->set('mail.password', 'top-secret', 'mail', 'string', true);

    $stored = SystemSetting::query()->where('key', 'mail.password')->value('value');

    expect($stored)->not->toBe('top-secret')
        ->and($service->get('mail.password'))->toBe('top-secret');
});

it('masks encrypted settings in the settings screen and leaves blanks unchanged', function () {
    $actor = User::factory()->withRole('System Administrator')->create();
    $service = app(SystemSettingService::class);
    $service->set('mail.password', 'top-secret', 'mail', 'string', true);

    $setting = SystemSetting::query()->where('key', 'mail.password')->firstOrFail();

    $this->actingAs($actor);

    Livewire::test(SettingsIndex::class)
        ->assertSet('values.'.$setting->id, '')
        ->assertDontSee('top-secret')
        ->set('values.'.$setting->id, '')
        ->call('save')
        ->assertHasNoErrors();

    expect($service->get('mail.password'))->toBe('top-secret');
});

it('updates a document sequence through the administration screen', function () {
    $actor = User::factory()->withRole('System Administrator')->create();
    $sequence = app(DocumentSequenceService::class)->create(DocumentType::MaterialRequisition, 'MR', 2026);

    $this->actingAs($actor);

    Livewire::test(SequencesIndex::class)
        ->call('openEdit', $sequence->id)
        ->set('form.next_number', 42)
        ->set('form.padding', 4)
        ->set('form.reset_policy', 'never')
        ->call('save')
        ->assertHasNoErrors();

    $sequence->refresh();

    expect($sequence->next_number)->toBe(42)
        ->and($sequence->padding)->toBe(4)
        ->and($sequence->reset_policy)->toBe('never')
        ->and($sequence->preview())->toBe('MR/2026/0042');
});

it('prevents unauthorized users from editing document sequences', function () {
    $actor = User::factory()->withRole('Store Officer')->create();
    $sequence = app(DocumentSequenceService::class)->create(DocumentType::MaterialRequisition, 'MR', 2026);

    $this->actingAs($actor);

    Livewire::test(SequencesIndex::class)->assertForbidden();
});
