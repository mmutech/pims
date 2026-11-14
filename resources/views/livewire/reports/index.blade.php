<div class="space-y-6">
    <x-page-header title="Reports" description="Operational and management reporting across inventory, procurement, warehouses, projects and audit. Every figure is scoped to your access." />

    @forelse ($groups as $group => $entry)
        <flux:card class="space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <flux:heading size="lg">{{ $group }}</flux:heading>
                    <flux:subheading>{{ count($entry['reports']) }} report{{ count($entry['reports']) === 1 ? '' : 's' }} available</flux:subheading>
                </div>
                <flux:button :href="route($entry['route'])" wire:navigate variant="filled" icon="chart-bar">
                    Open {{ strtolower($group) }} reports
                </flux:button>
            </div>

            <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                @foreach ($entry['reports'] as $report)
                    <a href="{{ route($entry['route'], ['report' => $report['key']]) }}" wire:navigate class="group rounded-lg border border-zinc-200 p-4 transition hover:border-zinc-300 dark:border-zinc-700 dark:hover:border-zinc-600">
                        <div class="font-medium text-zinc-900 group-hover:text-navy-700 dark:text-white dark:group-hover:text-navy-300">{{ $report['title'] }}</div>
                        <div class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ $report['description'] }}</div>
                        <div class="mt-2 text-xs font-medium text-zinc-400 dark:text-zinc-500">{{ $report['key'] }}</div>
                    </a>
                @endforeach
            </div>
        </flux:card>
    @empty
        <flux:card>
            <div class="py-10 text-center text-zinc-500 dark:text-zinc-400">
                No reports are available for your role. Ask an administrator for reporting permissions.
            </div>
        </flux:card>
    @endforelse
</div>
