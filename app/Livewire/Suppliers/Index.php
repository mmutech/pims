<?php

namespace App\Livewire\Suppliers;

use App\Domain\Supplier\Models\Supplier;
use App\Domain\Supplier\Services\SupplierService;
use App\DTOs\Supplier\SupplierData;
use App\Enums\SupplierStatus;
use App\Livewire\Concerns\HandlesDomainActions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Suppliers')]
class Index extends Component
{
    use HandlesDomainActions, WithPagination;

    public string $search = '';

    public string $statusFilter = '';

    public bool $showForm = false;

    public ?int $editingId = null;

    /** @var array<string, mixed> */
    public array $form = [];

    public bool $showDeleteModal = false;

    public ?int $deletingId = null;

    public string $deletingName = '';

    public bool $showStatusModal = false;

    public ?int $statusSupplierId = null;

    public string $statusSupplierName = '';

    public string $newStatus = '';

    public string $statusReason = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Supplier::class);
        $this->resetForm();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function resetForm(): void
    {
        $this->editingId = null;
        $this->form = [
            'code' => '',
            'name' => '',
            'legal_name' => '',
            'tax_identification_number' => '',
            'registration_number' => '',
            'email' => '',
            'phone' => '',
            'website' => '',
            'address' => '',
            'city' => '',
            'state' => '',
            'country' => 'Nigeria',
            'postal_code' => '',
            'contact_person' => '',
            'notes' => '',
        ];
        $this->resetValidation();
    }

    public function openCreate(): void
    {
        $this->authorize('create', Supplier::class);
        $this->resetForm();
        $this->showForm = true;
    }

    public function openEdit(int $supplierId): void
    {
        $supplier = Supplier::findOrFail($supplierId);
        $this->authorize('update', $supplier);

        $this->editingId = $supplier->id;
        $this->form = [
            'code' => $supplier->code,
            'name' => $supplier->name,
            'legal_name' => (string) $supplier->legal_name,
            'tax_identification_number' => (string) $supplier->tax_identification_number,
            'registration_number' => (string) $supplier->registration_number,
            'email' => (string) $supplier->email,
            'phone' => (string) $supplier->phone,
            'website' => (string) $supplier->website,
            'address' => (string) $supplier->address,
            'city' => (string) $supplier->city,
            'state' => (string) $supplier->state,
            'country' => (string) $supplier->country,
            'postal_code' => (string) $supplier->postal_code,
            'contact_person' => (string) $supplier->contact_person,
            'notes' => (string) $supplier->notes,
        ];
        $this->resetValidation();
        $this->showForm = true;
    }

    public function save(SupplierService $service): void
    {
        $supplier = $this->editingId !== null ? Supplier::findOrFail($this->editingId) : null;

        $supplier !== null
            ? $this->authorize('update', $supplier)
            : $this->authorize('create', Supplier::class);

        $this->validate([
            'form.code' => ['required', 'string', 'max:30', Rule::unique('suppliers', 'code')->ignore($supplier?->id)],
            'form.name' => ['required', 'string', 'max:200'],
            'form.legal_name' => ['nullable', 'string', 'max:200'],
            'form.tax_identification_number' => [
                'nullable', 'string', 'max:50',
                Rule::unique('suppliers', 'tax_identification_number')->ignore($supplier?->id),
            ],
            'form.registration_number' => ['nullable', 'string', 'max:50'],
            'form.email' => ['nullable', 'email', 'max:150'],
            'form.phone' => ['nullable', 'string', 'max:30'],
            'form.website' => ['nullable', 'url', 'max:200'],
            'form.address' => ['nullable', 'string', 'max:1000'],
            'form.city' => ['nullable', 'string', 'max:100'],
            'form.state' => ['nullable', 'string', 'max:100'],
            'form.country' => ['nullable', 'string', 'max:100'],
            'form.postal_code' => ['nullable', 'string', 'max:20'],
            'form.contact_person' => ['nullable', 'string', 'max:150'],
            'form.notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $payload = $this->form;
        $payload['status'] = $supplier?->status->value ?? SupplierStatus::Pending->value;

        $data = SupplierData::fromArray($payload);

        $saved = $this->runDomainAction(
            fn () => $supplier !== null
                ? $service->update($supplier, $data, auth()->user())
                : $service->create($data, auth()->user()),
            $supplier !== null ? 'Supplier updated.' : 'Supplier registered.',
        );

        if ($saved) {
            $this->showForm = false;
            $this->resetForm();
        }
    }

    public function approve(int $supplierId, SupplierService $service): void
    {
        $supplier = Supplier::findOrFail($supplierId);
        $this->authorize('approve', $supplier);

        $this->runDomainAction(
            fn () => $service->approve($supplier, auth()->user()),
            'Supplier approved and activated.',
        );
    }

    public function openStatusModal(int $supplierId): void
    {
        $supplier = Supplier::findOrFail($supplierId);
        $this->authorize('update', $supplier);

        $this->statusSupplierId = $supplier->id;
        $this->statusSupplierName = $supplier->name;
        $this->newStatus = $supplier->status->value;
        $this->statusReason = '';
        $this->resetValidation();
        $this->showStatusModal = true;
    }

    public function changeStatus(SupplierService $service): void
    {
        $supplier = Supplier::findOrFail($this->statusSupplierId);
        $this->authorize('update', $supplier);

        $this->validate([
            'newStatus' => ['required', Rule::enum(SupplierStatus::class)],
            'statusReason' => ['nullable', 'string', 'max:1000'],
        ]);

        $status = SupplierStatus::from($this->newStatus);
        $reason = trim($this->statusReason) !== '' ? trim($this->statusReason) : null;

        if ($this->runDomainAction(
            fn () => $service->changeStatus($supplier, $status, auth()->user(), $reason),
            "Supplier status changed to {$status->label()}.",
        )) {
            $this->showStatusModal = false;
            $this->statusSupplierId = null;
            $this->statusSupplierName = '';
            $this->newStatus = '';
            $this->statusReason = '';
        }
    }

    public function confirmDelete(int $supplierId): void
    {
        $supplier = Supplier::findOrFail($supplierId);
        $this->authorize('delete', $supplier);

        $this->deletingId = $supplier->id;
        $this->deletingName = $supplier->name;
        $this->showDeleteModal = true;
    }

    public function delete(SupplierService $service): void
    {
        $supplier = Supplier::findOrFail($this->deletingId);
        $this->authorize('delete', $supplier);

        if ($this->runDomainAction(fn () => $service->delete($supplier), 'Supplier deactivated and archived.')) {
            $this->showDeleteModal = false;
            $this->deletingId = null;
            $this->deletingName = '';
            $this->resetPage();
        }
    }

    public function render()
    {
        $suppliers = Supplier::query()
            ->withCount(['contacts', 'addresses', 'documents', 'evaluations'])
            ->search($this->search)
            ->when($this->statusFilter !== '', fn (Builder $query) => $query->where('status', $this->statusFilter))
            ->orderBy('name')
            ->paginate(15);

        return view('livewire.suppliers.index', [
            'suppliers' => $suppliers,
            'statusOptions' => SupplierStatus::cases(),
        ]);
    }
}
