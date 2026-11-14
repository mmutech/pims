<?php

namespace Tests\Fixtures;

use App\Domain\Workflow\Contracts\Approvable;
use App\Domain\Workflow\Models\ApprovalRequest;
use Illuminate\Database\Eloquent\Model;

/**
 * Lightweight document used to exercise the approval engine without a domain module.
 */
class FakeApprovableDocument extends Model implements Approvable
{
    public $timestamps = false;

    public $incrementing = false;

    protected $table = 'users';

    protected $guarded = [];

    /** @var array<string, mixed> */
    private array $approvalData = [];

    private string $documentType = 'test_document';

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function makeDocument(array $attributes = [], int $id = 1, string $type = 'test_document'): self
    {
        $document = new self;
        $document->exists = true;
        $document->setAttribute($document->getKeyName(), $id);
        $document->approvalData = $attributes;
        $document->documentType = $type;

        return $document;
    }

    public function approvalDocumentType(): string
    {
        return $this->documentType;
    }

    /**
     * @return array<string, mixed>
     */
    public function approvalAttributes(): array
    {
        return $this->approvalData;
    }

    public function onApprovalStatusChanged(ApprovalRequest $request): void
    {
        // No-op fixture: tests assert on the approval tables directly.
    }
}
