@props(['title', 'description' => null])

<div class="flex flex-wrap items-start justify-between gap-4">
    <div class="min-w-0">
        <flux:heading size="xl">{{ $title }}</flux:heading>
        @if ($description)
            <flux:subheading>{{ $description }}</flux:subheading>
        @endif
    </div>

    @isset($actions)
        <div class="flex shrink-0 flex-wrap items-center gap-2">
            {{ $actions }}
        </div>
    @endisset
</div>
