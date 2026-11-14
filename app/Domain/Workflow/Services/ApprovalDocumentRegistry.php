<?php

namespace App\Domain\Workflow\Services;

use InvalidArgumentException;

final class ApprovalDocumentRegistry
{
    /** @var array<string, class-string> */
    private array $documents = [];

    public function __construct()
    {
        foreach ((array) config('approvals.documents', []) as $type => $class) {
            if (is_string($type) && is_string($class) && class_exists($class)) {
                $this->documents[$type] = $class;
            }
        }
    }

    public function register(string $documentType, string $class): void
    {
        if (! class_exists($class)) {
            throw new InvalidArgumentException("Approval document class [{$class}] does not exist.");
        }

        $this->documents[$documentType] = $class;
    }

    /**
     * @return class-string|null
     */
    public function resolve(string $documentType): ?string
    {
        return $this->documents[$documentType] ?? null;
    }

    /**
     * @return array<string, class-string>
     */
    public function all(): array
    {
        return $this->documents;
    }

    /**
     * @return list<string>
     */
    public function types(): array
    {
        return array_keys($this->documents);
    }
}
