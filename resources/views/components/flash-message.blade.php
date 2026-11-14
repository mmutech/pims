@props(['message' => null])

@if ($message)
    <flux:callout variant="success" icon="check-circle" class="mb-4">
        <flux:callout.text>{{ $message }}</flux:callout.text>
    </flux:callout>
@endif

@if (session('status'))
    <flux:callout variant="success" icon="check-circle" class="mb-4">
        <flux:callout.text>{{ session('status') }}</flux:callout.text>
    </flux:callout>
@endif
