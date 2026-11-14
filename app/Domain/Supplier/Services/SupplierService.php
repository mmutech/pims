<?php

namespace App\Domain\Supplier\Services;

use App\Domain\Administration\Models\SystemSetting;
use App\Domain\Supplier\Models\Supplier;
use App\Domain\Supplier\Models\SupplierAddress;
use App\Domain\Supplier\Models\SupplierContact;
use App\Domain\Supplier\Models\SupplierDocument;
use App\Domain\Supplier\Models\SupplierEvaluation;
use App\DTOs\Supplier\SupplierData;
use App\Enums\SupplierAddressType;
use App\Enums\SupplierStatus;
use App\Models\User;
use App\Support\Decimal;
use App\Support\Permissions;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class SupplierService
{
    public function create(SupplierData $data, User $actor): Supplier
    {
        return Supplier::create([
            ...$data->toArray(),
            'created_by' => $actor->id,
            'is_approved' => false,
            'approved_at' => null,
            'approved_by' => null,
        ]);
    }

    public function update(Supplier $supplier, SupplierData $data, User $actor): Supplier
    {
        if ($supplier->code !== $data->code) {
            throw new DomainException('The supplier code is immutable once created.');
        }

        if ($supplier->status === SupplierStatus::Blacklisted && $data->status !== SupplierStatus::Blacklisted) {
            throw new DomainException('Blacklisted suppliers can only be reinstated by a Super Administrator.');
        }

        $supplier->update($data->toArray());

        return $supplier->refresh();
    }

    public function delete(Supplier $supplier): void
    {
        if ($supplier->batches()->exists()) {
            throw new DomainException('Supplier cannot be deleted while item batches reference it.');
        }

        $supplier->update(['status' => SupplierStatus::Inactive]);
        $supplier->delete();
    }

    public function approve(Supplier $supplier, User $actor): Supplier
    {
        if (! $actor->can(Permissions::SUPPLIERS_APPROVE)) {
            throw new AuthorizationException('You are not permitted to approve suppliers.');
        }

        if ($this->preventsSelfApproval() && $supplier->created_by === $actor->id) {
            throw new DomainException('Segregation of duties: you cannot approve a supplier you registered.');
        }

        if ($supplier->status === SupplierStatus::Blacklisted) {
            throw new DomainException('Blacklisted suppliers cannot be approved.');
        }

        $supplier->update([
            'status' => SupplierStatus::Active,
            'is_approved' => true,
            'approved_at' => now(),
            'approved_by' => $actor->id,
        ]);

        return $supplier->refresh();
    }

    public function changeStatus(Supplier $supplier, SupplierStatus $status, User $actor, ?string $reason = null): Supplier
    {
        if ($status === SupplierStatus::Blacklisted && ! $actor->isSuperAdmin()) {
            throw new AuthorizationException('Only a Super Administrator can blacklist a supplier.');
        }

        if ($supplier->status === SupplierStatus::Blacklisted && $status !== SupplierStatus::Blacklisted && ! $actor->isSuperAdmin()) {
            throw new AuthorizationException('Only a Super Administrator can reinstate a blacklisted supplier.');
        }

        if ($status === SupplierStatus::Active && ! $supplier->is_approved) {
            throw new DomainException('A supplier must be approved before it can be active.');
        }

        if (in_array($status, [SupplierStatus::Suspended, SupplierStatus::Blacklisted], true) && blank($reason)) {
            throw new DomainException('A reason is required when suspending or blacklisting a supplier.');
        }

        $supplier->update(['status' => $status]);

        if ($reason !== null) {
            $supplier->notes = trim(($supplier->notes ?? '')."\n[{$status->value}] ".$reason);
            $supplier->save();
        }

        return $supplier->refresh();
    }

    public function addContact(Supplier $supplier, array $attributes): SupplierContact
    {
        return DB::transaction(function () use ($supplier, $attributes): SupplierContact {
            if ((bool) ($attributes['is_primary'] ?? false)) {
                $supplier->contacts()->update(['is_primary' => false]);
            }

            return $supplier->contacts()->create($attributes);
        });
    }

    public function addAddress(Supplier $supplier, array $attributes): SupplierAddress
    {
        return DB::transaction(function () use ($supplier, $attributes): SupplierAddress {
            if ((bool) ($attributes['is_primary'] ?? false)) {
                $supplier->addresses()->update(['is_primary' => false]);
            }

            $attributes['type'] = $attributes['type'] instanceof SupplierAddressType
                ? $attributes['type']->value
                : ($attributes['type'] ?? SupplierAddressType::Registered->value);

            return $supplier->addresses()->create($attributes);
        });
    }

    public function addDocument(Supplier $supplier, array $attributes): SupplierDocument
    {
        return $supplier->documents()->create($attributes);
    }

    public function removeContact(Supplier $supplier, SupplierContact $contact): void
    {
        abort_unless($contact->supplier_id === $supplier->id, 404);

        $contact->delete();
    }

    public function removeAddress(Supplier $supplier, SupplierAddress $address): void
    {
        abort_unless($address->supplier_id === $supplier->id, 404);

        $address->delete();
    }

    public function removeDocument(Supplier $supplier, SupplierDocument $document): void
    {
        abort_unless($document->supplier_id === $supplier->id, 404);

        $document->delete();
    }

    /**
     * @param  array{quality_score: string|float, delivery_score: string|float, pricing_score: string|float, service_score: string|float}  $scores
     */
    public function addEvaluation(Supplier $supplier, array $scores, User $evaluator, ?string $comments = null): SupplierEvaluation
    {
        foreach ($scores as $score) {
            if (Decimal::compare((string) $score, '0', 2) < 0 || Decimal::compare((string) $score, '5', 2) > 0) {
                throw new DomainException('Supplier evaluation scores must be between 0 and 5.');
            }
        }

        $total = Decimal::add(
            Decimal::add((string) $scores['quality_score'], (string) $scores['delivery_score'], 2),
            Decimal::add((string) $scores['pricing_score'], (string) $scores['service_score'], 2),
            2,
        );
        $overall = Decimal::divide($total, '4', 2);

        $evaluation = $supplier->evaluations()->create([
            'evaluation_date' => now()->toDateString(),
            'quality_score' => (string) $scores['quality_score'],
            'delivery_score' => (string) $scores['delivery_score'],
            'pricing_score' => (string) $scores['pricing_score'],
            'service_score' => (string) $scores['service_score'],
            'overall_score' => $overall,
            'evaluator_id' => $evaluator->id,
            'comments' => $comments,
        ]);

        $supplier->update(['rating' => $overall]);

        return $evaluation;
    }

    private function preventsSelfApproval(): bool
    {
        $value = SystemSetting::where('key', 'security.prevent_self_approval')->value('value');

        return $value === null ? true : (bool) $value;
    }
}
