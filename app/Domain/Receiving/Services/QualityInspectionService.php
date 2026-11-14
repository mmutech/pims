<?php

namespace App\Domain\Receiving\Services;

use App\Domain\Administration\Services\DocumentSequenceService;
use App\Domain\Receiving\Models\DeliveryVerificationItem;
use App\Domain\Receiving\Models\QualityInspection;
use App\Domain\Receiving\Models\QualityInspectionFinding;
use App\Domain\Receiving\Models\QualityInspectionItem;
use App\Domain\Receiving\Models\StoreReceiptAdvice;
use App\Domain\Receiving\Models\StoreReceiptAdviceItem;
use App\DTOs\Receiving\QualityInspectionData;
use App\DTOs\Receiving\QualityInspectionItemData;
use App\Enums\DocumentType;
use App\Enums\InspectionMethod;
use App\Enums\InspectionResult;
use App\Enums\QualityInspectionStatus;
use App\Enums\ReceiptStatus;
use App\Models\User;
use App\Support\Decimal;
use DomainException;
use Illuminate\Support\Facades\DB;

class QualityInspectionService
{
    public function __construct(
        private readonly DocumentSequenceService $sequences,
    ) {}

    public function start(
        StoreReceiptAdvice $sra,
        User $actor,
        ?string $method = null,
        ?string $sampleSize = null,
    ): QualityInspection {
        if ($sra->status !== ReceiptStatus::PendingInspection) {
            throw new DomainException('This receipt advice is not awaiting quality inspection.');
        }

        $open = $sra->inspections()
            ->whereIn('status', [
                QualityInspectionStatus::Pending->value,
                QualityInspectionStatus::InProgress->value,
            ])
            ->first();

        if ($open !== null) {
            return $open->load('items');
        }

        $sra->loadMissing('items.item', 'verification.items');

        $verifiedByLine = $this->verifiedQuantities($sra);

        $inspectable = $sra->items->filter(function (StoreReceiptAdviceItem $item) use ($verifiedByLine): bool {
            return $item->item?->requires_quality_inspection
                && Decimal::isPositive($verifiedByLine[$item->id] ?? '0');
        });

        if ($inspectable->isEmpty()) {
            throw new DomainException('No received lines require quality inspection.');
        }

        return DB::transaction(function () use ($sra, $actor, $method, $sampleSize, $inspectable, $verifiedByLine): QualityInspection {
            $inspection = QualityInspection::create([
                'document_number' => $this->sequences->nextNumber(DocumentType::QualityInspection),
                'sra_id' => $sra->id,
                'delivery_verification_id' => $sra->verification?->id,
                'warehouse_id' => $sra->warehouse_id,
                'inspector_id' => $actor->id,
                'inspection_date' => now()->toDateString(),
                'status' => QualityInspectionStatus::Pending,
                'method' => $method !== null ? InspectionMethod::tryFrom($method) : null,
                'sample_size' => $sampleSize !== null ? Decimal::normalize($sampleSize) : null,
            ]);

            foreach ($inspectable as $sraItem) {
                QualityInspectionItem::create([
                    'quality_inspection_id' => $inspection->id,
                    'sra_item_id' => $sraItem->id,
                    'inspected_quantity' => $verifiedByLine[$sraItem->id] ?? '0',
                    'accepted_quantity' => '0',
                    'rejected_quantity' => '0',
                    'quarantined_quantity' => '0',
                    'result' => InspectionResult::Pending,
                ]);
            }

            return $inspection->refresh()->load('items.sraItem.item');
        });
    }

    public function record(QualityInspection $inspection, QualityInspectionData $data, User $actor): QualityInspection
    {
        if ($inspection->isComplete()) {
            throw new DomainException('This quality inspection has already been completed.');
        }

        $inspection->loadMissing('items.sraItem.item');

        $this->validateItems($inspection, $data->items);

        return DB::transaction(function () use ($inspection, $data, $actor): QualityInspection {
            $results = [];

            foreach ($data->items as $itemData) {
                /** @var QualityInspectionItem $item */
                $item = $inspection->items->firstWhere('sra_item_id', $itemData->sraItemId);

                $inspected = Decimal::normalize($itemData->inspectedQuantity);
                $accepted = Decimal::normalize($itemData->acceptedQuantity);
                $rejected = Decimal::normalize($itemData->rejectedQuantity);
                $quarantined = Decimal::normalize($itemData->quarantinedQuantity);

                $result = $this->resultFor($inspected, $accepted);

                $item->forceFill([
                    'inspected_quantity' => $inspected,
                    'accepted_quantity' => $accepted,
                    'rejected_quantity' => $rejected,
                    'quarantined_quantity' => $quarantined,
                    'result' => $result,
                    'notes' => $itemData->notes,
                ])->save();

                $item->findings()->delete();

                foreach ($itemData->findings as $finding) {
                    if (blank($finding->description)) {
                        continue;
                    }

                    QualityInspectionFinding::create([
                        'quality_inspection_item_id' => $item->id,
                        'finding_type' => $finding->findingType,
                        'severity' => $finding->severity,
                        'description' => $finding->description,
                        'evidence_path' => $finding->evidencePath,
                        'created_by' => $actor->id,
                    ]);
                }

                $results[] = $result;
            }

            $status = $this->overallStatus($results);
            $inspection->forceFill([
                'status' => $status,
                'summary' => $data->summary,
                'completed_at' => now(),
                'method' => $data->method !== null
                    ? InspectionMethod::tryFrom($data->method)
                    : $inspection->method,
                'sample_size' => $data->sampleSize !== null
                    ? Decimal::normalize($data->sampleSize)
                    : $inspection->sample_size,
            ])->save();

            $inspection->sra->forceFill(['status' => ReceiptStatus::Inspected])->save();

            return $inspection->refresh()->load('items.findings');
        });
    }

    /**
     * @return array<int, string>
     */
    private function verifiedQuantities(StoreReceiptAdvice $sra): array
    {
        $verification = $sra->verification;

        if ($verification === null) {
            return $sra->items
                ->mapWithKeys(fn (StoreReceiptAdviceItem $item) => [$item->id => (string) $item->delivered_quantity])
                ->all();
        }

        return $verification->items()
            ->get()
            ->mapWithKeys(fn (DeliveryVerificationItem $item) => [$item->sra_item_id => (string) $item->verified_quantity])
            ->all();
    }

    /**
     * @param  list<QualityInspectionItemData>  $items
     */
    private function validateItems(QualityInspection $inspection, array $items): void
    {
        if ($items === []) {
            throw new DomainException('An inspection result must contain at least one line.');
        }

        $seen = [];

        foreach ($items as $itemData) {
            /** @var QualityInspectionItem|null $item */
            $item = $inspection->items->firstWhere('sra_item_id', $itemData->sraItemId);

            if ($item === null) {
                throw new DomainException('An inspection line references an unknown receipt line.');
            }

            if (isset($seen[$item->id])) {
                throw new DomainException('Each inspection line may only be recorded once.');
            }

            $seen[$item->id] = true;

            $inspected = Decimal::normalize($itemData->inspectedQuantity);
            $accepted = Decimal::normalize($itemData->acceptedQuantity);
            $rejected = Decimal::normalize($itemData->rejectedQuantity);
            $quarantined = Decimal::normalize($itemData->quarantinedQuantity);

            if (Decimal::isNegative($inspected) || Decimal::isNegative($accepted)
                || Decimal::isNegative($rejected) || Decimal::isNegative($quarantined)) {
                throw new DomainException('Inspection quantities cannot be negative.');
            }

            if (Decimal::compare($inspected, $item->inspected_quantity) > 0) {
                throw new DomainException('Inspected quantity cannot exceed the verified quantity.');
            }

            $accounted = Decimal::add(
                Decimal::add($accepted, $rejected, Decimal::QUANTITY_SCALE),
                $quarantined,
                Decimal::QUANTITY_SCALE,
            );

            if (Decimal::compare($accounted, $inspected) !== 0) {
                throw new DomainException('Accepted, rejected and quarantined quantities must equal the inspected quantity.');
            }
        }

        if (count($seen) !== $inspection->items->count()) {
            throw new DomainException('Every inspection line must be recorded.');
        }
    }

    private function resultFor(string $inspected, string $accepted): InspectionResult
    {
        if (Decimal::isZero($inspected)) {
            return InspectionResult::Failed;
        }

        if (Decimal::compare($accepted, $inspected) === 0) {
            return InspectionResult::Passed;
        }

        if (Decimal::isZero($accepted)) {
            return InspectionResult::Failed;
        }

        return InspectionResult::PartiallyPassed;
    }

    /**
     * @param  list<InspectionResult>  $results
     */
    private function overallStatus(array $results): QualityInspectionStatus
    {
        if ($results !== [] && collect($results)->every(fn (InspectionResult $result) => $result === InspectionResult::Passed)) {
            return QualityInspectionStatus::Passed;
        }

        if ($results !== [] && collect($results)->every(fn (InspectionResult $result) => $result === InspectionResult::Failed)) {
            return QualityInspectionStatus::Failed;
        }

        return QualityInspectionStatus::PartiallyPassed;
    }
}
