@props(['message' => null, 'type' => 'success'])

@if ($message)
    <flux:callout
        :variant="$type === 'danger' ? 'danger' : 'success'"
        :icon="$type === 'danger' ? 'exclamation-triangle' : 'check-circle'"
        class="mb-4"
    >
        <flux:callout.text>{{ $message }}</flux:callout.text>
    </flux:callout>
@endif
