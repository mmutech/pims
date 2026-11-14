<?php

namespace App\Support\Concerns;

use DomainException;
use Illuminate\Database\Eloquent\Model;

/**
 * Business codes are the human key on documents and must never change.
 */
trait HasImmutableCode
{
    public static function bootHasImmutableCode(): void
    {
        static::updating(function (Model $model): void {
            if ($model->isDirty('code')) {
                throw new DomainException('The code field is immutable once created.');
            }
        });
    }
}
