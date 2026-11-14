<?php

namespace App\Livewire\Suppliers;

use App\Domain\Supplier\Models\Supplier;
use App\Domain\Supplier\Services\SupplierService;
use App\Enums\SupplierAddressType;
use App\Livewire\Concerns\HandlesDomainActions;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Supplier')]
class Show extends Component
{
    use HandlesDomainActions;

    public Supplier $supplier;

    /** @var array<string, mixed> */
    public array $contactForm = [];

    /** @var array<string, mixed> */
    public array $addressForm = [];

    /** @var array<string, mixed> */
    public array $documentForm = [];

    /** @var array<string, mixed> */
    public array $evaluationForm = [];

    public function mount(Supplier $supplier): void
    {
        $this->supplier = $supplier;
        $this->authorize('view', $this->supplier);

        $this->resetContactForm();
        $this->resetAddressForm();
        $this->resetDocumentForm();
        $this->resetEvaluationForm();
    }

    public function resetContactForm(): void
    {
        $this->contactForm = [
            'name' => '',
            'position' => '',
            'email' => '',
            'phone' => '',
            'is_primary' => false,
        ];
        $this->resetValidation();
    }

    public function resetAddressForm(): void
    {
        $this->addressForm = [
            'type' => SupplierAddressType::Registered->value,
            'line1' => '',
            'line2' => '',
            'city' => '',
            'state' => '',
            'country' => 'Nigeria',
            'postal_code' => '',
            'is_primary' => false,
        ];
        $this->resetValidation();
    }

    public function resetDocumentForm(): void
    {
        $this->documentForm = [
            'document_type' => '',
            'document_number' => '',
            'file_path' => '',
            'expires_at' => '',
        ];
        $this->resetValidation();
    }

    public function resetEvaluationForm(): void
    {
        $this->evaluationForm = [
            'quality_score' => '3.00',
            'delivery_score' => '3.00',
            'pricing_score' => '3.00',
            'service_score' => '3.00',
            'comments' => '',
        ];
        $this->resetValidation();
    }

    public function addContact(SupplierService $service): void
    {
        $this->authorize('update', $this->supplier);

        $this->validate([
            'contactForm.name' => ['required', 'string', 'max:150'],
            'contactForm.position' => ['nullable', 'string', 'max:100'],
            'contactForm.email' => ['nullable', 'email', 'max:150'],
            'contactForm.phone' => ['nullable', 'string', 'max:30'],
            'contactForm.is_primary' => ['boolean'],
        ]);

        if ($this->runDomainAction(fn () => $service->addContact($this->supplier, $this->contactForm), 'Contact added.')) {
            $this->resetContactForm();
        }
    }

    public function removeContact(int $contactId, SupplierService $service): void
    {
        $this->authorize('update', $this->supplier);

        $contact = $this->supplier->contacts()->findOrFail($contactId);

        $this->runDomainAction(fn () => $service->removeContact($this->supplier, $contact), 'Contact removed.');
    }

    public function addAddress(SupplierService $service): void
    {
        $this->authorize('update', $this->supplier);

        $this->validate([
            'addressForm.type' => ['required', Rule::enum(SupplierAddressType::class)],
            'addressForm.line1' => ['required', 'string', 'max:255'],
            'addressForm.line2' => ['nullable', 'string', 'max:255'],
            'addressForm.city' => ['nullable', 'string', 'max:100'],
            'addressForm.state' => ['nullable', 'string', 'max:100'],
            'addressForm.country' => ['nullable', 'string', 'max:100'],
            'addressForm.postal_code' => ['nullable', 'string', 'max:20'],
            'addressForm.is_primary' => ['boolean'],
        ]);

        if ($this->runDomainAction(fn () => $service->addAddress($this->supplier, $this->addressForm), 'Address added.')) {
            $this->resetAddressForm();
        }
    }

    public function removeAddress(int $addressId, SupplierService $service): void
    {
        $this->authorize('update', $this->supplier);

        $address = $this->supplier->addresses()->findOrFail($addressId);

        $this->runDomainAction(fn () => $service->removeAddress($this->supplier, $address), 'Address removed.');
    }

    public function addDocument(SupplierService $service): void
    {
        $this->authorize('update', $this->supplier);

        $this->validate([
            'documentForm.document_type' => ['required', 'string', 'max:50'],
            'documentForm.document_number' => ['nullable', 'string', 'max:100'],
            'documentForm.file_path' => ['required', 'string', 'max:500'],
            'documentForm.expires_at' => ['nullable', 'date'],
        ]);

        $attributes = $this->documentForm;
        $attributes['expires_at'] = $this->documentForm['expires_at'] !== '' ? $this->documentForm['expires_at'] : null;
        $attributes['uploaded_by'] = auth()->id();

        if ($this->runDomainAction(fn () => $service->addDocument($this->supplier, $attributes), 'Document recorded.')) {
            $this->resetDocumentForm();
        }
    }

    public function removeDocument(int $documentId, SupplierService $service): void
    {
        $this->authorize('update', $this->supplier);

        $document = $this->supplier->documents()->findOrFail($documentId);

        $this->runDomainAction(fn () => $service->removeDocument($this->supplier, $document), 'Document removed.');
    }

    public function addEvaluation(SupplierService $service): void
    {
        $this->authorize('update', $this->supplier);

        $this->validate([
            'evaluationForm.quality_score' => ['required', 'numeric', 'between:0,5'],
            'evaluationForm.delivery_score' => ['required', 'numeric', 'between:0,5'],
            'evaluationForm.pricing_score' => ['required', 'numeric', 'between:0,5'],
            'evaluationForm.service_score' => ['required', 'numeric', 'between:0,5'],
            'evaluationForm.comments' => ['nullable', 'string', 'max:2000'],
        ]);

        $scores = [
            'quality_score' => (string) $this->evaluationForm['quality_score'],
            'delivery_score' => (string) $this->evaluationForm['delivery_score'],
            'pricing_score' => (string) $this->evaluationForm['pricing_score'],
            'service_score' => (string) $this->evaluationForm['service_score'],
        ];

        $comments = trim((string) $this->evaluationForm['comments']) !== ''
            ? trim((string) $this->evaluationForm['comments'])
            : null;

        if ($this->runDomainAction(
            fn () => $service->addEvaluation($this->supplier, $scores, auth()->user(), $comments),
            'Supplier evaluation recorded.',
        )) {
            $this->resetEvaluationForm();
        }
    }

    public function render()
    {
        $this->supplier->refresh();

        return view('livewire.suppliers.show', [
            'contacts' => $this->supplier->contacts()->orderByDesc('is_primary')->orderBy('name')->get(),
            'addresses' => $this->supplier->addresses()->orderByDesc('is_primary')->get(),
            'documents' => $this->supplier->documents()->latest('id')->get(),
            'evaluations' => $this->supplier->evaluations()->with('evaluator')->latest('evaluation_date')->get(),
            'addressTypeOptions' => SupplierAddressType::cases(),
            'documentTypes' => ['CAC Certificate', 'Tax Clearance', 'TIN Certificate', 'Bank Reference', 'Insurance Certificate', 'ISO Certificate', 'Other'],
        ]);
    }
}
