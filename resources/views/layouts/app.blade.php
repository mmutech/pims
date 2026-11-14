<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="Procurement and Inventory Management System for electricity distribution: warehouses, catalog, suppliers, and procurement controls.">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">

        <title>{{ isset($title) ? $title.' · '.config('app.name') : config('app.name') }}</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @fluxAppearance
    </head>
    <body class="min-h-dvh bg-zinc-50 text-zinc-800 antialiased dark:bg-zinc-900 dark:text-zinc-200">
        <a href="#main-content" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:rounded-md focus:bg-white focus:px-4 focus:py-2 focus:text-sm focus:font-medium focus:text-zinc-900 focus:shadow-lg dark:focus:bg-zinc-800 dark:focus:text-white">Skip to content</a>
        <flux:sidebar sticky collapsible="mobile" class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.brand name="{{ config('app.name') }}" href="{{ route('dashboard') }}" wire:navigate>
                <x-slot name="logo" class="relative flex aspect-square size-6 items-center justify-center rounded-md bg-navy-900 text-white">
                    <flux:icon name="cube" variant="micro" class="size-4" />
                    <span aria-hidden="true" class="absolute -bottom-0.5 -end-0.5 size-2 rounded-full bg-secondary-400 ring-2 ring-zinc-50 dark:ring-zinc-900"></span>
                </x-slot>
            </flux:sidebar.brand>

            <flux:sidebar.nav>
                @can('dashboard.view')
                    <flux:sidebar.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                        Dashboard
                    </flux:sidebar.item>
                @endcan

                @can('viewAny', App\Domain\Organization\Models\BusinessUnit::class || 'viewAny', App\Domain\Organization\Models\Department::class || 'viewAny', App\Domain\Organization\Models\CostCenter::class || 'viewAny', App\Domain\Organization\Models\Project::class || 'viewAny', App\Domain\Warehouse\Models\Warehouse::class)
                    <flux:sidebar.group heading="Organization" expandable :expanded="request()->routeIs('organization.*')">
                        <flux:sidebar.item icon="building-office-2" :href="route('organization.business-units.index')" :current="request()->routeIs('organization.business-units.*')" wire:navigate>Business Units</flux:sidebar.item>
                        <flux:sidebar.item icon="building-office" :href="route('organization.departments.index')" :current="request()->routeIs('organization.departments.*')" wire:navigate>Departments</flux:sidebar.item>
                        <flux:sidebar.item icon="calculator" :href="route('organization.cost-centers.index')" :current="request()->routeIs('organization.cost-centers.*')" wire:navigate>Cost Centres</flux:sidebar.item>
                        <flux:sidebar.item icon="briefcase" :href="route('organization.projects.index')" :current="request()->routeIs('organization.projects.*')" wire:navigate>Projects</flux:sidebar.item>
                        <flux:sidebar.item icon="building-storefront" :href="route('warehouses.index')" :current="request()->routeIs('warehouses.*')" wire:navigate>
                            Warehouses
                        </flux:sidebar.item>
                    </flux:sidebar.group>
                @endcan

                @can('viewAny', App\Domain\Catalog\Models\Item::class)
                    <flux:sidebar.group heading="Catalog" expandable :expanded="request()->routeIs('catalog.*')">
                        <flux:sidebar.item icon="cube" :href="route('catalog.items.index')" :current="request()->routeIs('catalog.items.*')" wire:navigate>Items</flux:sidebar.item>
                        <flux:sidebar.item icon="tag" :href="route('catalog.categories.index')" :current="request()->routeIs('catalog.categories.*')" wire:navigate>Categories</flux:sidebar.item>
                        <flux:sidebar.item icon="scale" :href="route('catalog.units.index')" :current="request()->routeIs('catalog.units.*')" wire:navigate>Units of Measure</flux:sidebar.item>
                    </flux:sidebar.group>
                @endcan

                @can('viewAny', App\Domain\Supplier\Models\Supplier::class)
                    <flux:sidebar.item icon="truck" :href="route('suppliers.index')" :current="request()->routeIs('suppliers.*')" wire:navigate>
                        Suppliers
                    </flux:sidebar.item>
                @endcan

                @if (
                    auth()->user()->can('viewAny', App\Domain\Procurement\Models\PurchaseRequisition::class)
                    || auth()->user()->can('viewAny', App\Domain\Procurement\Models\RequestForQuotation::class)
                    || auth()->user()->can('viewAny', App\Domain\Procurement\Models\SupplierQuotation::class)
                    || auth()->user()->can('viewAny', App\Domain\Procurement\Models\PurchaseOrder::class)
                )
                    <flux:sidebar.group heading="Procurement" expandable :expanded="request()->routeIs('procurement.*')">
                        @can('viewAny', App\Domain\Procurement\Models\PurchaseRequisition::class)
                            <flux:sidebar.item icon="clipboard-document-list" :href="route('procurement.requisitions.index')" :current="request()->routeIs('procurement.requisitions.*')" wire:navigate>Requisitions</flux:sidebar.item>
                        @endcan
                        @can('viewAny', App\Domain\Procurement\Models\RequestForQuotation::class)
                            <flux:sidebar.item icon="document-magnifying-glass" :href="route('procurement.rfqs.index')" :current="request()->routeIs('procurement.rfqs.*')" wire:navigate>Requests for Quotation</flux:sidebar.item>
                        @endcan
                        @can('viewAny', App\Domain\Procurement\Models\SupplierQuotation::class)
                            <flux:sidebar.item icon="receipt-percent" :href="route('procurement.quotations.index')" :current="request()->routeIs('procurement.quotations.*') || request()->routeIs('procurement.evaluations.*')" wire:navigate>Supplier Quotations</flux:sidebar.item>
                        @endcan
                        @can('viewAny', App\Domain\Procurement\Models\PurchaseOrder::class)
                            <flux:sidebar.item icon="shopping-cart" :href="route('procurement.orders.index')" :current="request()->routeIs('procurement.orders.*')" wire:navigate>Purchase Orders</flux:sidebar.item>
                        @endcan
                    </flux:sidebar.group>
                @endif

                @if (
                    auth()->user()->can('viewAny', App\Domain\Receiving\Models\StoreReceiptAdvice::class)
                    || auth()->user()->can('viewAny', App\Domain\Receiving\Models\GoodsReceivedNote::class)
                    || auth()->user()->can('viewAny', App\Domain\Receiving\Models\SupplierReturn::class)
                )
                    <flux:sidebar.group heading="Receiving" expandable :expanded="request()->routeIs('receiving.*')">
                        @can('viewAny', App\Domain\Receiving\Models\StoreReceiptAdvice::class)
                            <flux:sidebar.item icon="inbox-arrow-down" :href="route('receiving.sra.index')" :current="request()->routeIs('receiving.sra.*')" wire:navigate>Receipt Advices</flux:sidebar.item>
                        @endcan
                        @can('viewAny', App\Domain\Receiving\Models\GoodsReceivedNote::class)
                            <flux:sidebar.item icon="document-check" :href="route('receiving.grn.index')" :current="request()->routeIs('receiving.grn.*')" wire:navigate>Goods Received Notes</flux:sidebar.item>
                        @endcan
                        @can('viewAny', App\Domain\Receiving\Models\SupplierReturn::class)
                            <flux:sidebar.item icon="arrow-uturn-left" :href="route('receiving.returns.index')" :current="request()->routeIs('receiving.returns.*')" wire:navigate>Supplier Returns</flux:sidebar.item>
                        @endcan
                    </flux:sidebar.group>
                @endif

                @if (
                    auth()->user()->can('viewAny', App\Domain\Inventory\Models\InventoryBalance::class)
                    || auth()->user()->can('viewAny', App\Domain\Inventory\Models\StockTransaction::class)
                )
                    <flux:sidebar.group heading="Inventory" expandable :expanded="request()->routeIs('inventory.*')">
                        @can('viewAny', App\Domain\Inventory\Models\InventoryBalance::class)
                            <flux:sidebar.item icon="squares-2x2" :href="route('inventory.balances.index')" :current="request()->routeIs('inventory.balances.*')" wire:navigate>Balances</flux:sidebar.item>
                        @endcan
                        @can('viewAny', App\Domain\Inventory\Models\StockTransaction::class)
                            <flux:sidebar.item icon="arrows-right-left" :href="route('inventory.transactions.index')" :current="request()->routeIs('inventory.transactions.*')" wire:navigate>Stock Transactions</flux:sidebar.item>
                        @endcan
                    </flux:sidebar.group>
                @endif

                @if (
                    auth()->user()->can('viewAny', App\Domain\Stocktaking\Models\StockAdjustment::class)
                    || auth()->user()->can('viewAny', App\Domain\Stocktaking\Models\StockCount::class)
                    || auth()->user()->can('viewAny', App\Domain\Stocktaking\Models\StockReconciliation::class)
                )
                    <flux:sidebar.group heading="Inventory Control" expandable :expanded="request()->routeIs('stocktaking.*')">
                        @can('viewAny', App\Domain\Stocktaking\Models\StockAdjustment::class)
                            <flux:sidebar.item icon="adjustments-horizontal" :href="route('stocktaking.adjustments.index')" :current="request()->routeIs('stocktaking.adjustments.*')" wire:navigate>Stock Adjustments</flux:sidebar.item>
                        @endcan
                        @can('viewAny', App\Domain\Stocktaking\Models\StockCount::class)
                            <flux:sidebar.item icon="clipboard-document-check" :href="route('stocktaking.counts.index')" :current="request()->routeIs('stocktaking.counts.*')" wire:navigate>Stock Counts</flux:sidebar.item>
                        @endcan
                        @can('viewAny', App\Domain\Stocktaking\Models\StockReconciliation::class)
                            <flux:sidebar.item icon="calculator" :href="route('stocktaking.reconciliations.index')" :current="request()->routeIs('stocktaking.reconciliations.*')" wire:navigate>Reconciliations</flux:sidebar.item>
                        @endcan
                    </flux:sidebar.group>
                @endif

                @if (
                    auth()->user()->can('viewAny', App\Domain\Distribution\Models\MaterialRequisition::class)
                    || auth()->user()->can('viewAny', App\Domain\Distribution\Models\StockIssue::class)
                    || auth()->user()->can('viewAny', App\Domain\Distribution\Models\StockTransfer::class)
                    || auth()->user()->can('viewAny', App\Domain\Distribution\Models\MaterialReturn::class)
                    || auth()->user()->can('distribution.project_consumption.view')
                )
                    <flux:sidebar.group heading="Distribution" expandable :expanded="request()->routeIs('distribution.*')">
                        @can('viewAny', App\Domain\Distribution\Models\MaterialRequisition::class)
                            <flux:sidebar.item icon="clipboard-document-list" :href="route('distribution.requisitions.index')" :current="request()->routeIs('distribution.requisitions.*')" wire:navigate>Material Requisitions</flux:sidebar.item>
                        @endcan
                        @can('viewAny', App\Domain\Distribution\Models\StockIssue::class)
                            <flux:sidebar.item icon="arrow-up-tray" :href="route('distribution.issues.index')" :current="request()->routeIs('distribution.issues.*')" wire:navigate>Stock Issues</flux:sidebar.item>
                        @endcan
                        @can('viewAny', App\Domain\Distribution\Models\StockTransfer::class)
                            <flux:sidebar.item icon="arrows-right-left" :href="route('distribution.transfers.index')" :current="request()->routeIs('distribution.transfers.*')" wire:navigate>Stock Transfers</flux:sidebar.item>
                        @endcan
                        @can('viewAny', App\Domain\Distribution\Models\MaterialReturn::class)
                            <flux:sidebar.item icon="arrow-uturn-left" :href="route('distribution.returns.index')" :current="request()->routeIs('distribution.returns.*')" wire:navigate>Material Returns</flux:sidebar.item>
                        @endcan
                        @can('distribution.project_consumption.view')
                            <flux:sidebar.item icon="chart-bar" :href="route('distribution.project-consumption.index')" :current="request()->routeIs('distribution.project-consumption.*')" wire:navigate>Project Consumption</flux:sidebar.item>
                        @endcan
                    </flux:sidebar.group>
                @endif

                @can('viewAny', App\Domain\Workflow\Models\ApprovalRequest::class)
                    <flux:sidebar.group heading="Approvals" expandable :expanded="request()->routeIs('approvals.*') || request()->routeIs('delegations.*') || request()->routeIs('workflows.*')">
                        <flux:sidebar.item icon="inbox" :href="route('approvals.index')" :current="request()->routeIs('approvals.index') || request()->routeIs('approvals.show')" wire:navigate>My Approvals</flux:sidebar.item>
                        <flux:sidebar.item icon="clock" :href="route('approvals.history')" :current="request()->routeIs('approvals.history')" wire:navigate>History</flux:sidebar.item>
                        <flux:sidebar.item icon="user-plus" :href="route('delegations.index')" :current="request()->routeIs('delegations.*')" wire:navigate>Delegations</flux:sidebar.item>
                        @can('viewAny', App\Domain\Workflow\Models\ApprovalFlow::class)
                            <flux:sidebar.item icon="arrows-right-left" :href="route('workflows.index')" :current="request()->routeIs('workflows.*')" wire:navigate>Workflows</flux:sidebar.item>
                        @endcan
                    </flux:sidebar.group>
                @endcan

                @if (
                    auth()->user()->can('reports.inventory.view')
                    || auth()->user()->can('reports.procurement.view')
                    || auth()->user()->can('reports.warehouse.view')
                    || auth()->user()->can('reports.project.view')
                    || auth()->user()->can('reports.audit.view')
                )
                    <flux:sidebar.group heading="Reports" expandable :expanded="request()->routeIs('reports.*')">
                        <flux:sidebar.item icon="chart-bar" :href="route('reports.index')" :current="request()->routeIs('reports.index')" wire:navigate>Report Centre</flux:sidebar.item>
                        @can('reports.inventory.view')
                            <flux:sidebar.item icon="squares-2x2" :href="route('reports.inventory')" :current="request()->routeIs('reports.inventory')" wire:navigate>Inventory</flux:sidebar.item>
                        @endcan
                        @can('reports.procurement.view')
                            <flux:sidebar.item icon="shopping-cart" :href="route('reports.procurement')" :current="request()->routeIs('reports.procurement')" wire:navigate>Procurement</flux:sidebar.item>
                        @endcan
                        @can('reports.warehouse.view')
                            <flux:sidebar.item icon="building-storefront" :href="route('reports.warehouses')" :current="request()->routeIs('reports.warehouses')" wire:navigate>Warehouses</flux:sidebar.item>
                        @endcan
                        @can('reports.project.view')
                            <flux:sidebar.item icon="briefcase" :href="route('reports.projects')" :current="request()->routeIs('reports.projects')" wire:navigate>Projects</flux:sidebar.item>
                        @endcan
                        @can('reports.audit.view')
                            <flux:sidebar.item icon="shield-check" :href="route('reports.audit')" :current="request()->routeIs('reports.audit')" wire:navigate>Audit</flux:sidebar.item>
                        @endcan
                    </flux:sidebar.group>
                @endif

                @if (auth()->user()->can('viewAny', App\Models\User::class) || auth()->user()->can('viewAny', Spatie\Permission\Models\Role::class) || auth()->user()->can('audit.view'))
                    <flux:sidebar.group heading="Administration" expandable :expanded="request()->routeIs('administration.*')">
                        @can('viewAny', App\Models\User::class)
                            <flux:sidebar.item icon="users" :href="route('administration.users.index')" :current="request()->routeIs('administration.users.*')" wire:navigate>Users</flux:sidebar.item>
                        @endcan
                        @can('viewAny', Spatie\Permission\Models\Role::class)
                            <flux:sidebar.item icon="shield-check" :href="route('administration.roles.index')" :current="request()->routeIs('administration.roles.*')" wire:navigate>Roles</flux:sidebar.item>
                        @endcan
                        @can('viewAny', App\Domain\Administration\Models\DocumentSequence::class)
                            <flux:sidebar.item icon="document-text" :href="route('administration.sequences.index')" :current="request()->routeIs('administration.sequences.*')" wire:navigate>Document Sequences</flux:sidebar.item>
                        @endcan
                        @can('viewAny', App\Domain\Administration\Models\SystemSetting::class)
                            <flux:sidebar.item icon="cog-6-tooth" :href="route('administration.settings.index')" :current="request()->routeIs('administration.settings.*')" wire:navigate>Settings</flux:sidebar.item>
                        @endcan
                        @can('audit.view')
                            <flux:sidebar.item icon="clipboard-document-list" :href="route('administration.activity.index')" :current="request()->routeIs('administration.activity.*')" wire:navigate>Activity Log</flux:sidebar.item>
                        @endcan
                    </flux:sidebar.group>
                @endif
            </flux:sidebar.nav>

            <flux:spacer />

            <flux:dropdown position="top" align="start">
                <flux:sidebar.profile :name="auth()->user()->name" :initials="str(auth()->user()->name)->explode(' ')->map(fn ($part) => str($part)->substr(0, 1))->take(2)->implode('')" />
                <flux:menu>
                    <flux:menu.item icon="user-circle" :href="route('profile')" wire:navigate>My Profile</flux:menu.item>
                    <flux:menu.separator />
                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full">Log Out</flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:sidebar>

        <flux:header class="border-b border-zinc-200 bg-white/80 backdrop-blur dark:border-zinc-700 dark:bg-zinc-900/80">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-3" inset="left" />

            <div class="flex min-w-0 items-center gap-2">
                <span class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ config('app.name') }}</span>
                <span class="text-zinc-300 dark:text-zinc-600">/</span>
                <span class="truncate text-sm font-semibold text-zinc-800 dark:text-zinc-100">
                    {{ str(request()->route()?->getName() ?? 'Dashboard')->before('.index')->replace('.', ' / ')->headline() }}
                </span>
            </div>

            <flux:spacer />

            <flux:dropdown position="bottom" align="end">
                <flux:button variant="ghost" square icon="sun" aria-label="Change appearance" />
                <flux:menu>
                    <flux:menu.radio.group>
                        <flux:menu.radio x-on:click="$flux.appearance = 'light'">Light</flux:menu.radio>
                        <flux:menu.radio x-on:click="$flux.appearance = 'dark'">Dark</flux:menu.radio>
                        <flux:menu.radio x-on:click="$flux.appearance = 'system'">System</flux:menu.radio>
                    </flux:menu.radio.group>
                </flux:menu>
            </flux:dropdown>

            <flux:dropdown position="bottom" align="end">
                <flux:profile :name="auth()->user()->name" :initials="str(auth()->user()->name)->explode(' ')->map(fn ($part) => str($part)->substr(0, 1))->take(2)->implode('')" />
                <flux:menu>
                    <flux:menu.item icon="user-circle" :href="route('profile')" wire:navigate>My Profile</flux:menu.item>
                    <flux:menu.separator />
                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full">Log Out</flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:header>

        <flux:main role="main" id="main-content" tabindex="-1">
            {{ $slot }}
        </flux:main>

        @fluxScripts
    </body>
</html>
