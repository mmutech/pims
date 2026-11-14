<?php

namespace App\Livewire\Concerns;

use DomainException;
use Illuminate\Auth\Access\AuthorizationException;

trait HandlesDomainActions
{
    public ?string $feedback = null;

    public string $feedbackType = 'success';

    protected function success(string $message): void
    {
        $this->feedback = $message;
        $this->feedbackType = 'success';
    }

    protected function error(string $message): void
    {
        $this->feedback = $message;
        $this->feedbackType = 'danger';
    }

    /**
     * Run a domain service call and translate expected domain failures into inline feedback.
     */
    protected function runDomainAction(callable $callback, string $successMessage): bool
    {
        try {
            $callback();

            $this->success($successMessage);

            return true;
        } catch (DomainException|AuthorizationException $exception) {
            $this->error($exception->getMessage());

            return false;
        }
    }
}
