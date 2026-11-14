<?php

namespace App\Livewire\Receiving\Sra;

use App\Domain\Procurement\Models\PurchaseOrder;
use App\Domain\Receiving\Models\StoreReceiptAdvice;
use App\Domain\Receiving\Services\StoreReceiptAdviceService;
use App\DTOs\Receiving\StoreReceiptAdviceData;
use App\Enums\PurchaseOrderStatus;
use App\Livewire\Concerns\HandlesDomainActions;
use App\Support\Decimal;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Store Receipt Advice')]
class Form extends Component
{
    use HandlesDomainActions;

    public ?int $sraId = null;

    /** @var array<string, mixed> */
    public array $form = [];

    /** @var list<array<string, mixed>> */
    public array $items = [];

    public function mount(?StoreReceiptAdvice $sra = null): void
    {
        if ($sra !== null && $sra->exists) {
            $this->authorize('update', $sra);

            $this->sraId = $sra->id;
            $this->form = [
                'purchase_order_id' => (string) $sra->purchase_order_id,
                'expected_delivery_date' => $sra->expected_delivery_date?->toDateString() ?? '',
                'vehicle_number' => (string) $sra->vehicle_number,
                'driver_name' => (string) $sra->driver_name,
                'driver_phone' => (string) $sra->driver_phone,
                'delivery_note_number' => (string) $sra->delivery_note_number,
            ];

            $this->items = $sra->items->map(fn ($item) => [
                'purchase_order_item_id' => (string) $item->purchase_order_item_id,
                'item_id' => (string) $item->item_id,
                'unit_of_measure_id' => (string) $item->unit_of_measure_id,
                'label' => $item->item?->code.' — '.$item->item?->name,
                'unit' => $item->unitOfMeasure?->code,
                'expected_quantity' => (string) $item->expected_quantity,
                'delivered_quantity' => (string) $item->delivered_quantity,
                'batch_number' => (string) $item->batch_number,
                'serial_numbers' => implode(',', $item->serial_numbers ?? []),
                'condition_notes' => (string) $item->condition_notes,
            ])->all();

            return;
        }

        $this->authorize('create', StoreReceiptAdvice::class);

        $order = PurchaseOrder::query()
            ->with('items.item', 'items.unitOfMeasure')
            ->find(request()->integer('purchase_order'));

        $this->form = [
            'purchase_order_id' => $order?->id !== null ? (string) $order->id : '',
            'expected_delivery_date' => now()->toDateString(),
            'vehicle_number' => '',
            'driver_name' => '',
            'driver_phone' => '',
            'delivery_note_number' => '',
        ];
        $this->items = [];

        if ($order !== null) {
            $this->hydrateFromOrder($order);
        }
    }

    public function updatedFormPurchaseOrderId(): void
    {
        $order = PurchaseOrder::query()
            ->with('items.item', 'items.unitOfMeasure')
            ->find((int) $this->form['purchase_order_id']);

        if ($order !== null) {
            $this->hydrateFromOrder($order);
        }
    }

    public function save(StoreReceiptAdviceService $service)
    {
        $sra = $this->sraId !== null ? StoreReceiptAdvice::findOrFail($this->sraId) : null;

        $sra !== null
            ? $this->authorize('update', $sra)
            : $this->authorize('create', StoreReceiptAdvice::class);

        $this->validate([
            'form.purchase_order_id' => ['required', 'integer', 'exists:purchase_orders,id'],
            'form.expected_delivery_date' => ['nullable', 'date'],
            'form.vehicle_number' => ['nullable', 'string', 'max:50'],
            'form.driver_name' => ['nullable', 'string', 'max:100'],
            'form.driver_phone' => ['nullable', 'string', 'max:30'],
            'form.delivery_note_number' => ['nullable', 'string', 'max:80'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.expected_quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.delivered_quantity' => ['required', 'numeric', 'min:0'],
            'items.*.batch_number' => ['nullable', 'string', 'max:100'],
            'items.*.condition_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $payload = [
            ...$this->form,
            'received_by' => auth()->id(),
            'items' => array_map(fn (array $item) => [
                'purchase_order_item_id' => (int) $item['purchase_order_item_id'],
                'expected_quantity' => (string) $item['expected_quantity'],
                'delivered_quantity' => (string) $item['delivered_quantity'],
                'batch_number' => $item['batch_number'] !== '' ? $item['batch_number'] : null,
                'serial_numbers' => array_values(array_filter(array_map('trim', explode(',', (string) $item['serial_numbers'])))),
                'condition_notes' => $item['condition_notes'] !== '' ? $item['condition_notes'] : null,
            ], $this->items),
        ];

        $data = StoreReceiptAdviceData::fromArray($payload);

        $saved = $this->runDomainAction(
            fn () => $sra !== null
                ? $service->update($sra, $data, auth()->user())
                : $service->create($data, auth()->user()),
            $sra !== null ? 'Receipt advice updated.' : 'Receipt advice created.',
        );

        if ($saved) {
            $savedSra = $sra ?? StoreReceiptAdvice::query()->latest('id')->first();

            return redirect()->route('receiving.sra.show', $savedSra);
        }

        return null;
    }

    public function render()
    {
        $orders = PurchaseOrder::query()
            ->with('supplier')
            ->whereIn('status', [PurchaseOrderStatus::Approved, PurchaseOrderStatus::PartiallyReceived])
            ->visibleTo(auth()->user())
            ->latest('id')
            ->get();

        return view('livewire.receiving.sra.form', [
            'orders' => $orders,
        ]);
    }

    private function hydrateFromOrder(PurchaseOrder $order): void
    {
        $this->items = $order->items
            ->filter(fn ($item) => Decimal::isPositive($item->remainingQuantity()))
            ->map(fn ($item) => [
                'purchase_order_item_id' => (string) $item->id,
                'item_id' => (string) $item->item_id,
                'unit_of_measure_id' => (string) $item->unit_of_measure_id,
                'label' => $item->item?->code.' — '.$item->item?->name,
                'unit' => $item->unitOfMeasure?->code,
                'expected_quantity' => (string) $item->remainingQuantity(),
                'delivered_quantity' => (string) $item->remainingQuantity(),
                'batch_number' => '',
                'serial_numbers' => '',
                'condition_notes' => '',
            ])
            ->values()
            ->all();
    }
}
