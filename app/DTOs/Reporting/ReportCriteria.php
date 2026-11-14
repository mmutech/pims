<?php

namespace App\DTOs\Reporting;

use Carbon\CarbonImmutable;

/**
 * Normalised report filters shared by every report screen, service and export.
 */
final class ReportCriteria
{
    public function __construct(
        public readonly ?string $from = null,
        public readonly ?string $to = null,
        public readonly ?int $warehouseId = null,
        public readonly ?int $itemId = null,
        public readonly ?int $categoryId = null,
        public readonly ?int $supplierId = null,
        public readonly ?int $projectId = null,
        public readonly ?string $status = null,
        public readonly ?string $search = null,
        public readonly ?string $logName = null,
        public readonly ?string $subjectType = null,
        public readonly int $movementWindowDays = 90,
        public readonly int $expiryWindowDays = 90,
        public readonly bool $mismatchesOnly = false,
        public readonly int $perPage = 20,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public static function fromArray(array $input): self
    {
        return new self(
            from: self::date($input['from'] ?? null),
            to: self::date($input['to'] ?? null),
            warehouseId: self::integer($input['warehouseId'] ?? null),
            itemId: self::integer($input['itemId'] ?? null),
            categoryId: self::integer($input['categoryId'] ?? null),
            supplierId: self::integer($input['supplierId'] ?? null),
            projectId: self::integer($input['projectId'] ?? null),
            status: self::text($input['status'] ?? null),
            search: self::text($input['search'] ?? null),
            logName: self::text($input['logName'] ?? null),
            subjectType: self::text($input['subjectType'] ?? null),
            movementWindowDays: self::window($input['movementWindowDays'] ?? null, 90),
            expiryWindowDays: self::window($input['expiryWindowDays'] ?? null, 90),
            mismatchesOnly: filter_var($input['mismatchesOnly'] ?? false, FILTER_VALIDATE_BOOL),
            perPage: min(200, max(10, (int) ($input['perPage'] ?? 20))),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'from' => $this->from,
            'to' => $this->to,
            'warehouseId' => $this->warehouseId,
            'itemId' => $this->itemId,
            'categoryId' => $this->categoryId,
            'supplierId' => $this->supplierId,
            'projectId' => $this->projectId,
            'status' => $this->status,
            'search' => $this->search,
            'logName' => $this->logName,
            'subjectType' => $this->subjectType,
            'movementWindowDays' => $this->movementWindowDays,
            'expiryWindowDays' => $this->expiryWindowDays,
            'mismatchesOnly' => $this->mismatchesOnly,
            'perPage' => $this->perPage,
        ], fn (mixed $value): bool => $value !== null && $value !== '');
    }

    public function withPerPage(int $perPage): self
    {
        return new self(
            from: $this->from,
            to: $this->to,
            warehouseId: $this->warehouseId,
            itemId: $this->itemId,
            categoryId: $this->categoryId,
            supplierId: $this->supplierId,
            projectId: $this->projectId,
            status: $this->status,
            search: $this->search,
            logName: $this->logName,
            subjectType: $this->subjectType,
            movementWindowDays: $this->movementWindowDays,
            expiryWindowDays: $this->expiryWindowDays,
            mismatchesOnly: $this->mismatchesOnly,
            perPage: $perPage,
        );
    }

    public function hasDateRange(): bool
    {
        return $this->from !== null || $this->to !== null;
    }

    public function fromDateTime(): ?CarbonImmutable
    {
        return $this->from !== null ? CarbonImmutable::parse($this->from)->startOfDay() : null;
    }

    public function toDateTime(): ?CarbonImmutable
    {
        return $this->to !== null ? CarbonImmutable::parse($this->to)->endOfDay() : null;
    }

    private static function date(mixed $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        try {
            return CarbonImmutable::parse((string) $value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private static function integer(mixed $value): ?int
    {
        if (blank($value)) {
            return null;
        }

        $integer = (int) $value;

        return $integer > 0 ? $integer : null;
    }

    private static function text(mixed $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        return mb_substr(trim((string) $value), 0, 120);
    }

    private static function window(mixed $value, int $default): int
    {
        if (blank($value)) {
            return $default;
        }

        return min(3650, max(1, (int) $value));
    }
}
