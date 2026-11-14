<?php

namespace App\Livewire\Procurement\Quotations;

use App\Domain\Procurement\Models\RequestForQuotation;
use App\Domain\Procurement\Models\SupplierQuotation;
use App\Domain\Procurement\Services\SupplierQuotationService;
use App\DTOs\Procurement\QuotationData;
use App\Enums\RequestForQuotationStatus;
use App\Livewire\Concerns\HandlesDomainActions;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Supplier Quotation')]
class Form extends Component
{
    use HandlesDomainActions;

    public ?int $quotationId = null;

    public string $rfqId = '';

    /** @var array<string, mixed> */
    public array $form = [];

    /** @var list<array<string, mixed>> */
    public array $items = [];

    public function mount(?SupplierQuotation $quotation = null): void
    {
        if ($quotation !== null && $quotation->exists) {
            $this->authorize('update', $quotation);

            $this->quotationId = $quotation->id;
            $this->rfqId = (string) $quotation->request_for_quotation_id;
            $this->form = [
                'supplier_id' => (string) $quotation->supplier_id,
                'currency' => $quotation->currency,
                'valid_until' => $quotation->valid_until?->toDateString() ?? '',
                'lead_time_days' => (string) ($quotation->lead_time_days ?? ''),
                'payment_terms' => (string) $quotation->payment_terms,
                'warranty_terms' => (string) $quotation->warranty_terms,
            ];
            $this->items = $quotation->items->map(fn ($item) => [
                'rfq_item_id' => $item->rfq_item_id,
                'quantity' => (string) $item->quantity,
                'unit_price' => (string) $item->unit_price,
                'tax_rate' => (string) $item->tax_rate,
                'discount_amount' => (string) $item->discount_amount,
            ])->all();

            return;
        }

        $this->authorize('create', SupplierQuotation::class);

        $this->form = [
            'supplier_id' => '',
            'currency' => 'NGN',
            'valid_until' => now()->addDays(30)->toDateString(),
            'lead_time_days' => '',
            'payment_terms' => '',
            'warranty_terms' => '',
        ];

        $this->rfqId = (string) request()->integer('rfq');

        if ($this->rfqId !== '') {
            $this->loadRfqItems();
        }
    }

    public function updatedRfqId(): void
    {
        $this->items = [];
        $this->form['supplier_id'] = '';
        $this->loadRfqItems();
    }

    public function save(SupplierQuotationService $service)
    {
        $quotation = $this->quotationId !== null ? SupplierQuotation::findOrFail($this->quotationId) : null;

        $quotation !== null
            ? $this->authorize('update', $quotation)
            : $this->authorize('create', SupplierQuotation::class);

        $this->validate([
            'rfqId' => ['required', 'integer', 'exists:request_for_quotations,id'],
            'form.supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'form.currency' => ['required', 'string', 'size:3'],
            'form.valid_until' => ['nullable', 'date'],
            'form.lead_time_days' => ['nullable', 'integer', 'min:0'],
            'form.payment_terms' => ['nullable', 'string', 'max:100'],
            'form.warranty_terms' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.tax_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'items.*.discount_amount' => ['required', 'numeric', 'min:0'],
        ]);

        $data = QuotationData::fromArray([
            ...$this->form,
            'request_for_quotation_id' => (int) $this->rfqId,
            'items' => $this->items,
        ]);

        $saved = $this->runDomainAction(
            fn () => $quotation !== null
                ? $service->update($quotation, $data, auth()->user())
                : $service->create($data, auth()->user()),
            $quotation !== null ? 'Quotation updated.' : 'Quotation captured.',
        );

        if ($saved) {
            $savedQuotation = $quotation ?? SupplierQuotation::query()->latest('id')->first();

            return redirect()->route('procurement.quotations.show', $savedQuotation);
        }

        return null;
    }

    private function loadRfqItems(): void
    {
        $rfq = RequestForQuotation::query()
            ->whereKey((int) $this->rfqId)
            ->whereIn('status', [
                RequestForQuotationStatus::Issued->value,
                RequestForQuotationStatus::Closed->value,
            ])
            ->with('items')
            ->first();

        if ($rfq === null) {
            return;
        }

        $this->items = $rfq->items->map(fn ($item) => [
            'rfq_item_id' => $item->id,
            'quantity' => (string) $item->quantity,
            'unit_price' => '0',
            'tax_rate' => '0',
            'discount_amount' => '0',
        ])->all();
    }

    public function render()
    {
        $rfq = $this->rfqId !== '' ? RequestForQuotation::with('items.item', 'items.unitOfMeasure', 'suppliers.supplier')->find((int) $this->rfqId) : null;

        $quotableSuppliers = $rfq !== null
            ? $rfq->suppliers
                ->filter(fn ($invite) => $this->quotationId !== null
                    ? $invite->supplier_id === (int) $this->form['supplier_id']
                    : ! SupplierQuotation::query()
                        ->where('request_for_quotation_id', $rfq->id)
                        ->where('supplier_id', $invite->supplier_id)
                        ->exists())
                ->pluck('supplier')
                ->filter()
            : collect();

        return view('livewire.procurement.quotations.form', [
            'rfq' => $rfq,
            'quotableSuppliers' => $quotableSuppliers,
            'openRfqs' => RequestForQuotation::query()
                ->whereIn('status', [
                    RequestForQuotationStatus::Issued->value,
                    RequestForQuotationStatus::Closed->value,
                ])
                ->whereHas('suppliers')
                ->latest('id')
                ->get(),
        ]);
    }
}
