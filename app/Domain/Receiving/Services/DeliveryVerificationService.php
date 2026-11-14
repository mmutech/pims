<?php

namespace App\Domain\Receiving\Services;

use App\Domain\Administration\Services\DocumentSequenceService;
use App\Domain\Receiving\Models\DeliveryVerification;
use App\Domain\Receiving\Models\DeliveryVerificationItem;
use App\Domain\Receiving\Models\StoreReceiptAdvice;
use App\Domain\Receiving\Models\StoreReceiptAdviceItem;
use App\DTOs\Receiving\DeliveryVerificationData;
use App\DTOs\Receiving\DeliveryVerificationItemData;
use App\Enums\DeliveryCondition;
use App\Enums\DeliveryVerificationStatus;
use App\Enums\DocumentType;
use App\Enums\ReceiptStatus;
use App\Models\User;
use App\Support\Decimal;
use DomainException;
use Illuminate\Support\Facades\DB;

class DeliveryVerificationService
{
    public function __construct(
        private readonly DocumentSequenceService $sequences,
    ) {}

    public function verify(StoreReceiptAdvice $sra, DeliveryVerificationData $data, User $actor): DeliveryVerification
    {
        if (! $sra->canBeVerified()) {
            throw new DomainException('This receipt advice is not awaiting delivery verification.');
        }

        if ($sra->verification()->exists()) {
            throw new DomainException('A delivery verification already exists for this receipt advice.');
        }

        $sra->loadMissing('items.item');

        $this->validateItems($sra, $data->items);

        return DB::transaction(function () use ($sra, $data, $actor): DeliveryVerification {
            $status = DeliveryVerificationStatus::Verified;
            $discrepancies = [];
            $requiresInspection = false;
            $summaries = [];

            $verification = DeliveryVerification::create([
                'document_number' => $this->sequences->nextNumber(DocumentType::DeliveryVerification),
                'sra_id' => $sra->id,
                'warehouse_id' => $sra->warehouse_id,
                'verified_by' => $actor->id,
                'verified_at' => now(),
                'status' => DeliveryVerificationStatus::Pending,
            ]);

            foreach ($data->items as $itemData) {
                /** @var StoreReceiptAdviceItem $sraItem */
                $sraItem = $sra->items->firstWhere('id', $itemData->sraItemId);

                $verified = Decimal::normalize($itemData->verifiedQuantity);
                $damaged = Decimal::normalize($itemData->damagedQuantity);
                $short = Decimal::subtract($sraItem->delivered_quantity, $verified, Decimal::QUANTITY_SCALE);

                if (Decimal::isNegative($short)) {
                    $short = Decimal::normalize('0');
                }

                $condition = $this->conditionFor($verified, $damaged, $short, $itemData->condition);

                DeliveryVerificationItem::create([
                    'verification_id' => $verification->id,
                    'sra_item_id' => $sraItem->id,
                    'verified_quantity' => $verified,
                    'damaged_quantity' => $damaged,
                    'short_quantity' => $short,
                    'condition' => $condition,
                    'notes' => $itemData->notes,
                ]);

                if (Decimal::isPositive($verified) && $sraItem->item?->requires_quality_inspection) {
                    $requiresInspection = true;
                }

                if (Decimal::isPositive($damaged) || Decimal::isPositive($short)) {
                    $status = DeliveryVerificationStatus::Discrepancy;
                    $discrepancies[] = sprintf(
                        '%s: verified %s, damaged %s, short %s.',
                        $sraItem->item?->name ?? 'Item',
                        $verified,
                        $damaged,
                        $short,
                    );
                }

                $summaries[] = sprintf(
                    '%s verified %s of %s.',
                    $sraItem->item?->name ?? 'Item',
                    $verified,
                    $sraItem->delivered_quantity,
                );
            }

            $verification->forceFill([
                'status' => $status,
                'discrepancy_summary' => $data->discrepancySummary
                    ?? ($discrepancies !== [] ? implode(' ', $discrepancies) : implode(' ', $summaries)),
            ])->save();

            $sra->forceFill([
                'verified_at' => now(),
                'status' => $requiresInspection
                    ? ReceiptStatus::PendingInspection
                    : ReceiptStatus::Verified,
            ])->save();

            return $verification->refresh()->load('items');
        });
    }

    /**
     * @param  list<DeliveryVerificationItemData>  $items
     */
    private function validateItems(StoreReceiptAdvice $sra, array $items): void
    {
        if ($items === []) {
            throw new DomainException('A delivery verification must contain at least one line.');
        }

        $seen = [];

        foreach ($items as $itemData) {
            /** @var StoreReceiptAdviceItem|null $sraItem */
            $sraItem = $sra->items->firstWhere('id', $itemData->sraItemId);

            if ($sraItem === null) {
                throw new DomainException('A verification line references an unknown receipt advice line.');
            }

            if (isset($seen[$sraItem->id])) {
                throw new DomainException('Each receipt advice line may only be verified once.');
            }

            $seen[$sraItem->id] = true;

            $verified = Decimal::normalize($itemData->verifiedQuantity);
            $damaged = Decimal::normalize($itemData->damagedQuantity);

            if (Decimal::isNegative($verified)) {
                throw new DomainException('Verified quantities cannot be negative.');
            }

            if (Decimal::compare($verified, $sraItem->delivered_quantity) > 0) {
                throw new DomainException('Verified quantity cannot exceed the delivered quantity.');
            }

            if (Decimal::isNegative($damaged) || Decimal::compare($damaged, $verified) > 0) {
                throw new DomainException('Damaged quantity must be between zero and the verified quantity.');
            }
        }

        if (count($seen) !== $sra->items->count()) {
            throw new DomainException('Every receipt advice line must be verified.');
        }
    }

    private function conditionFor(
        string $verified,
        string $damaged,
        string $short,
        ?string $requested,
    ): DeliveryCondition {
        if (Decimal::isPositive($damaged)) {
            return DeliveryCondition::Damaged;
        }

        if (Decimal::isPositive($short)) {
            return DeliveryCondition::Short;
        }

        if ($requested !== null) {
            $condition = DeliveryCondition::tryFrom($requested);

            if ($condition !== null) {
                return $condition;
            }
        }

        return DeliveryCondition::Good;
    }
}
