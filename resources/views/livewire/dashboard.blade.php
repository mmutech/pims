<div class="space-y-6">
    <x-page-header
        :title="'Welcome back, '.auth()->user()->name"
        description="Organization, warehouses, catalog, suppliers, administration, and configurable approval routing."
    />

    @if (! empty($kpis))
        <div class="space-y-3">
            <div class="flex items-center justify-between gap-3">
                <flux:heading size="lg">Key performance indicators</flux:heading>
                <flux:button size="sm" variant="ghost" :href="route('reports.index')" wire:navigate icon="chart-bar">Report centre</flux:button>
            </div>
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                @foreach ($kpis as $kpi)
                    <a href="{{ route($kpi['route']) }}" wire:navigate class="group">
                        <flux:card class="h-full border-navy-100 bg-navy-50 transition group-hover:border-navy-300 dark:border-navy-500/20 dark:bg-zinc-900 dark:group-hover:border-navy-400/40">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <flux:subheading>{{ $kpi['label'] }}</flux:subheading>
                                    <div class="mt-2 text-2xl font-semibold text-zinc-900 dark:text-white">{{ $kpi['value'] }}</div>
                                </div>
                                <span class="rounded-lg bg-secondary-50 p-2 text-secondary-700 dark:bg-secondary-950/30 dark:text-secondary-300">
                                    <flux:icon :name="$kpi['icon']" variant="outline" class="size-5" />
                                </span>
                            </div>
                            <p class="mt-3 text-xs text-zinc-500 dark:text-zinc-400">{{ $kpi['hint'] }}</p>
                        </flux:card>
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    @if ($stockMovementChart !== null)
        <flux:card>
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <flux:heading>Stock movement</flux:heading>
                    <flux:subheading>Inbound and outbound quantities over the last six months</flux:subheading>
                </div>
                <div class="flex items-center gap-4 text-xs text-zinc-600 dark:text-zinc-300">
                    <span class="inline-flex items-center gap-2">
                        <span class="size-2.5 rounded-sm bg-navy-600 dark:bg-navy-300"></span>
                        Inbound
                    </span>
                    <span class="inline-flex items-center gap-2">
                        <span class="size-2.5 rounded-sm bg-secondary-500"></span>
                        Outbound
                    </span>
                </div>
            </div>

            @if ($stockMovementChart['has_data'])
                <div
                    class="mt-6 grid h-48 grid-cols-6 gap-2 sm:gap-4"
                    role="img"
                    aria-label="Monthly inbound and outbound stock quantities"
                    aria-describedby="stock-movement-data"
                    data-chart="stock-movements"
                >
                    @foreach ($stockMovementChart['points'] as $point)
                        <div class="flex min-w-0 flex-col justify-end gap-2">
                            <div class="flex min-h-0 flex-1 items-end justify-center gap-1 border-b border-zinc-200 dark:border-zinc-700">
                                <span
                                    class="w-3 rounded-t-sm bg-navy-600 dark:bg-navy-300"
                                    style="height: {{ $point['inbound_height'] }}%"
                                    title="Inbound: {{ $point['inbound'] }} units"
                                ></span>
                                <span
                                    class="w-3 rounded-t-sm bg-secondary-500"
                                    style="height: {{ $point['outbound_height'] }}%"
                                    title="Outbound: {{ $point['outbound'] }} units"
                                ></span>
                            </div>
                            <span class="truncate text-center text-xs text-zinc-500 dark:text-zinc-400">{{ $point['label'] }}</span>
                        </div>
                    @endforeach
                </div>
                <ul id="stock-movement-data" class="sr-only">
                    @foreach ($stockMovementChart['points'] as $point)
                        <li>{{ $point['label'] }}: {{ $point['inbound'] }} units inbound, {{ $point['outbound'] }} units outbound</li>
                    @endforeach
                </ul>
            @else
                <p class="mt-6 rounded-lg bg-zinc-50 px-4 py-8 text-center text-sm text-zinc-500 dark:bg-zinc-800/60 dark:text-zinc-400">
                    No posted stock movements in the last six months.
                </p>
            @endif
        </flux:card>
    @endif

    <div class="grid gap-6 lg:grid-cols-2">
        @if ($warehouseSummary->isNotEmpty())
            <flux:card class="min-w-0">
                <div class="flex items-center justify-between">
                    <flux:heading>Warehouse overview</flux:heading>
                    <flux:button size="sm" variant="ghost" :href="route('warehouses.index')" wire:navigate>View all</flux:button>
                </div>

                <div class="mt-4 space-y-3">
                    @foreach ($warehouseSummary as $warehouse)
                        <div class="flex items-center justify-between gap-4 border-b border-zinc-100 pb-3 last:border-0 last:pb-0 dark:border-zinc-700">
                            <div class="min-w-0">
                                <div class="truncate text-sm font-medium text-zinc-800 dark:text-zinc-100">{{ $warehouse->name }}</div>
                                <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ $warehouse->code }} · {{ $warehouse->city ?? 'No city' }}</div>
                            </div>
                            <div class="flex shrink-0 items-center gap-2 text-xs text-zinc-500 dark:text-zinc-400">
                                <flux:badge size="sm">{{ $warehouse->locations_count }} locations</flux:badge>
                                <flux:badge size="sm" color="zinc">{{ $warehouse->access_grants_count }} users</flux:badge>
                            </div>
                        </div>
                    @endforeach
                </div>
            </flux:card>
        @endif

        @if ($lowStockItems->isNotEmpty())
            <flux:card class="min-w-0">
                <div class="flex items-center justify-between">
                    <flux:heading>Reorder watchlist</flux:heading>
                    <flux:button size="sm" variant="ghost" :href="route('catalog.items.index')" wire:navigate>Open catalog</flux:button>
                </div>

                <div class="mt-4 space-y-3">
                    @foreach ($lowStockItems as $item)
                        <div class="flex items-center justify-between gap-4 border-b border-zinc-100 pb-3 last:border-0 last:pb-0 dark:border-zinc-700">
                            <div class="min-w-0">
                                <div class="truncate text-sm font-medium text-zinc-800 dark:text-zinc-100">{{ $item->name }}</div>
                                <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ $item->code }} · reorder at {{ $item->reorder_level }}</div>
                            </div>
                            <flux:badge size="sm" color="orange">{{ $item->tracking_type->label() }}</flux:badge>
                        </div>
                    @endforeach
                </div>
            </flux:card>
        @endif
    </div>
</div>
