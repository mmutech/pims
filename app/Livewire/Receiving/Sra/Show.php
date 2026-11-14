<?php

namespace App\Livewire\Receiving\Sra;

use App\Domain\Receiving\Models\QualityInspection;
use App\Domain\Receiving\Models\StoreReceiptAdvice;
use App\Domain\Receiving\Services\DeliveryVerificationService;
use App\Domain\Receiving\Services\GoodsReceivedNoteService;
use App\Domain\Receiving\Services\QualityInspectionService;
use App\Domain\Receiving\Services\StoreReceiptAdviceService;
use App\DTOs\Receiving\DeliveryVerificationData;
use App\DTOs\Receiving\QualityInspectionData;
use App\Enums\DeliveryCondition;
use App\Enums\InspectionMethod;
use App\Livewire\Concerns\HandlesDomainActions;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Store Receipt Advice')]
class Show extends Component
{
    use HandlesDomainActions;

    public StoreReceiptAdvice $sra;

    public bool $showCancelModal = false;

    public string $cancellationReason = '';

    public bool $showVerificationModal = false;

    public string $discrepancySummary = '';

    /** @var list<array<string, mixed>> */
    public array $verificationLines = [];

    public bool $showInspectionModal = false;

    public ?int $inspectionId = null;

    public string $inspectionMethod = '';

    public string $inspectionSampleSize = '';

    public string $inspectionSummary = '';

    /** @var list<array<string, mixed>> */
    public array $inspectionLines = [];

    public function mount(StoreReceiptAdvice $sra): void
    {
        $this->authorize('view', $sra);

        $this->sra = $sra;
        $this->loadSra();
    }

    public function submit(StoreReceiptAdviceService $service): void
    {
        $this->authorize('submit', $this->sra);

        $this->runDomainAction(
            fn () => $service->submit($this->sra, auth()->user()),
            'Receipt advice submitted for verification.',
        );

        $this->loadSra();
    }

    public function cancel(StoreReceiptAdviceService $service): void
    {
        $this->authorize('cancel', $this->sra);

        $cancelled = $this->runDomainAction(
            fn () => $service->cancel($this->sra, auth()->user(), $this->cancellationReason),
            'Receipt advice cancelled.',
        );

        if ($cancelled) {
            $this->showCancelModal = false;
            $this->cancellationReason = '';
        }

        $this->loadSra();
    }

    public function openVerificationModal(): void
    {
        $this->authorize('verify', $this->sra);

        $verification = $this->sra->verification;
        $verified = $verification?->items->keyBy('sra_item_id') ?? collect();

        $this->verificationLines = $this->sra->items->map(function ($item) use ($verified): array {
            $line = $verified->get($item->id);

            return [
                'sra_item_id' => $item->id,
                'label' => $item->item?->code.' — '.$item->item?->name,
                'unit' => $item->unitOfMeasure?->code,
                'delivered_quantity' => (string) $item->delivered_quantity,
                'verified_quantity' => (string) ($line?->verified_quantity ?? $item->delivered_quantity),
                'damaged_quantity' => (string) ($line?->damaged_quantity ?? '0'),
                'condition' => $line?->condition?->value ?? DeliveryCondition::Good->value,
                'notes' => (string) ($line?->notes ?? ''),
            ];
        })->all();

        $this->discrepancySummary = (string) ($verification?->discrepancy_summary ?? '');
        $this->showVerificationModal = true;
    }

    public function verify(DeliveryVerificationService $service): void
    {
        $this->authorize('verify', $this->sra);

        $this->validate([
            'verificationLines' => ['required', 'array', 'min:1'],
            'verificationLines.*.verified_quantity' => ['required', 'numeric', 'min:0'],
            'verificationLines.*.damaged_quantity' => ['required', 'numeric', 'min:0'],
            'verificationLines.*.condition' => ['nullable', 'string', 'max:20'],
            'verificationLines.*.notes' => ['nullable', 'string', 'max:1000'],
            'discrepancySummary' => ['nullable', 'string', 'max:2000'],
        ]);

        $data = DeliveryVerificationData::fromArray([
            'sra_id' => $this->sra->id,
            'verified_by' => auth()->id(),
            'discrepancy_summary' => $this->discrepancySummary,
            'items' => array_map(fn (array $line): array => [
                'sra_item_id' => (int) $line['sra_item_id'],
                'verified_quantity' => (string) $line['verified_quantity'],
                'damaged_quantity' => (string) $line['damaged_quantity'],
                'condition' => $line['condition'] ?? null,
                'notes' => $line['notes'] ?? null,
            ], $this->verificationLines),
        ]);

        $verified = $this->runDomainAction(
            fn () => $service->verify($this->sra, $data, auth()->user()),
            'Delivery verified.',
        );

        if ($verified) {
            $this->showVerificationModal = false;
        }

        $this->loadSra();
    }

    public function startInspection(QualityInspectionService $service): void
    {
        $this->authorize('startInspection', $this->sra);

        $inspection = null;

        $started = $this->runDomainAction(
            function () use ($service, &$inspection): void {
                $inspection = $service->start(
                    $this->sra,
                    auth()->user(),
                    $this->inspectionMethod !== '' ? $this->inspectionMethod : null,
                    $this->inspectionSampleSize !== '' ? $this->inspectionSampleSize : null,
                );
            },
            'Quality inspection started.',
        );

        $this->loadSra();

        if (! $started || $inspection === null) {
            return;
        }

        $this->openInspectionModal($inspection->id);
    }

    public function openInspectionModal(int $inspectionId): void
    {
        $inspection = QualityInspection::query()
            ->with('items.sraItem.item', 'items.sraItem.unitOfMeasure')
            ->findOrFail($inspectionId);

        $this->authorize('view', $inspection);

        $this->inspectionId = $inspection->id;
        $this->inspectionMethod = $inspection->method?->value ?? '';
        $this->inspectionSampleSize = (string) ($inspection->sample_size ?? '');
        $this->inspectionSummary = (string) ($inspection->summary ?? '');

        $this->inspectionLines = $inspection->items->map(fn ($item): array => [
            'sra_item_id' => $item->sra_item_id,
            'label' => $item->sraItem?->item?->code.' — '.$item->sraItem?->item?->name,
            'unit' => $item->sraItem?->unitOfMeasure?->code,
            'inspected_quantity' => (string) $item->inspected_quantity,
            'accepted_quantity' => (string) $item->accepted_quantity,
            'rejected_quantity' => (string) $item->rejected_quantity,
            'quarantined_quantity' => (string) $item->quarantined_quantity,
            'notes' => (string) ($item->notes ?? ''),
        ])->all();

        $this->showInspectionModal = true;
    }

    public function recordInspection(QualityInspectionService $service): void
    {
        $inspection = QualityInspection::query()->findOrFail($this->inspectionId);

        $this->authorize('record', $inspection);

        $this->validate([
            'inspectionLines' => ['required', 'array', 'min:1'],
            'inspectionLines.*.inspected_quantity' => ['required', 'numeric', 'min:0'],
            'inspectionLines.*.accepted_quantity' => ['required', 'numeric', 'min:0'],
            'inspectionLines.*.rejected_quantity' => ['required', 'numeric', 'min:0'],
            'inspectionLines.*.quarantined_quantity' => ['required', 'numeric', 'min:0'],
            'inspectionLines.*.notes' => ['nullable', 'string', 'max:1000'],
            'inspectionSummary' => ['nullable', 'string', 'max:2000'],
        ]);

        $data = QualityInspectionData::fromArray([
            'sra_id' => $this->sra->id,
            'inspector_id' => auth()->id(),
            'method' => $this->inspectionMethod !== '' ? $this->inspectionMethod : null,
            'sample_size' => $this->inspectionSampleSize !== '' ? $this->inspectionSampleSize : null,
            'summary' => $this->inspectionSummary,
            'items' => array_map(fn (array $line): array => [
                'sra_item_id' => (int) $line['sra_item_id'],
                'inspected_quantity' => (string) $line['inspected_quantity'],
                'accepted_quantity' => (string) $line['accepted_quantity'],
                'rejected_quantity' => (string) $line['rejected_quantity'],
                'quarantined_quantity' => (string) $line['quarantined_quantity'],
                'notes' => $line['notes'] ?? null,
            ], $this->inspectionLines),
        ]);

        $recorded = $this->runDomainAction(
            fn () => $service->record($inspection, $data, auth()->user()),
            'Inspection result recorded.',
        );

        if ($recorded) {
            $this->showInspectionModal = false;
            $this->inspectionId = null;
        }

        $this->loadSra();
    }

    public function createGrn(GoodsReceivedNoteService $service)
    {
        $this->authorize('createGoodsReceivedNote', $this->sra);

        $grn = null;

        $created = $this->runDomainAction(
            function () use ($service, &$grn): void {
                $grn = $service->createFromSra($this->sra, auth()->user());
            },
            'Goods received note drafted.',
        );

        $this->loadSra();

        if ($created && $grn !== null) {
            return redirect()->route('receiving.grn.show', $grn);
        }

        return null;
    }

    public function render()
    {
        return view('livewire.receiving.sra.show', [
            'purchaseOrder' => $this->sra->purchaseOrder,
            'verification' => $this->sra->verification,
            'inspections' => $this->sra->inspections,
            'grns' => $this->sra->goodsReceivedNotes,
            'conditionOptions' => DeliveryCondition::cases(),
            'methodOptions' => InspectionMethod::cases(),
            'requiresInspection' => $this->sra->requiresQualityInspection(),
        ]);
    }

    private function loadSra(): void
    {
        $this->sra = $this->sra->fresh([
            'purchaseOrder',
            'supplier',
            'warehouse',
            'receiver',
            'items.item',
            'items.unitOfMeasure',
            'verification.items',
            'inspections.items.sraItem.item',
            'inspections.items.findings',
            'goodsReceivedNotes',
        ]);

        $this->sra->requiresQualityInspection();
    }
}
