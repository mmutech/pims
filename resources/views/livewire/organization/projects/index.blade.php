<div class="space-y-6">
    <x-page-header title="Projects" description="Projects consume stock and own budget across the organization.">
        <x-slot name="actions">
            @can('create', App\Domain\Organization\Models\Project::class)
                <flux:button variant="primary" icon="plus" wire:click="openCreate">New project</flux:button>
            @endcan
        </x-slot>
    </x-page-header>

    <x-domain-feedback :message="$feedback" :type="$feedbackType" />

    <flux:card>
        <div class="flex flex-wrap items-center gap-3">
            <flux:input wire:model.live.debounce.300ms="search" placeholder="Search by code or name" icon="magnifying-glass" class="max-w-sm" />
            <flux:select wire:model.live="statusFilter" class="max-w-xs">
                <option value="">All statuses</option>
                @foreach ($statusOptions as $status)
                    <option value="{{ $status->value }}">{{ $status->label() }}</option>
                @endforeach
            </flux:select>
            <flux:spacer />
            <span class="text-sm text-zinc-500 dark:text-zinc-400">{{ $projects->total() }} projects</span>
        </div>

        <flux:table :paginate="$projects" class="mt-4">
            <flux:table.columns>
                <flux:table.column>Code</flux:table.column>
                <flux:table.column>Project</flux:table.column>
                <flux:table.column>Department</flux:table.column>
                <flux:table.column>Manager</flux:table.column>
                <flux:table.column>Budget</flux:table.column>
                <flux:table.column>Status</flux:table.column>
                <flux:table.column align="end">Actions</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($projects as $project)
                    <flux:table.row :key="$project->id">
                        <flux:table.cell variant="strong">{{ $project->code }}</flux:table.cell>
                        <flux:table.cell>
                            <div class="font-medium text-zinc-800 dark:text-zinc-100">{{ $project->name }}</div>
                            <div class="text-xs text-zinc-500 dark:text-zinc-400">
                                {{ $project->members_count }} {{ Str::plural('member', $project->members_count) }}
                                @if ($project->start_date)
                                    &middot; started {{ $project->start_date->format('M Y') }}
                                @endif
                            </div>
                        </flux:table.cell>
                        <flux:table.cell>{{ $project->department?->name ?? '—' }}</flux:table.cell>
                        <flux:table.cell>{{ $project->manager?->name ?? '—' }}</flux:table.cell>
                        <flux:table.cell>
                            {{ $project->currency }} {{ number_format((float) $project->budget_amount, 2) }}
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="match ($project->status) {
                                App\Enums\ProjectStatus::Active => 'green',
                                App\Enums\ProjectStatus::Planned => 'blue',
                                App\Enums\ProjectStatus::OnHold => 'amber',
                                App\Enums\ProjectStatus::Completed => 'zinc',
                                App\Enums\ProjectStatus::Cancelled => 'red',
                            }">
                                {{ $project->status->label() }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell align="end">
                            <div class="flex justify-end gap-1">
                                @can('manageMembers', $project)
                                    <flux:button size="sm" variant="ghost" icon="user-group" aria-label="Manage members" wire:click="openMembersModal({{ $project->id }})" />
                                @endcan
                                @can('update', $project)
                                    @if (! $project->status->isTerminal())
                                        <flux:button size="sm" variant="ghost" icon="arrow-path" aria-label="Change status" wire:click="openStatusModal({{ $project->id }})" />
                                    @endif
                                    <flux:button size="sm" variant="ghost" icon="pencil-square" aria-label="Edit project" wire:click="openEdit({{ $project->id }})" />
                                @endcan
                                @can('delete', $project)
                                    <flux:button size="sm" variant="ghost" icon="trash" aria-label="Delete project" wire:click="confirmDelete({{ $project->id }})" />
                                @endcan
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="7" class="py-10 text-center text-zinc-500 dark:text-zinc-400">
                            No projects match your search.
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    <flux:modal wire:model="showForm" class="md:w-[44rem]">
        <form wire:submit="save" class="space-y-4">
            <div>
                <flux:heading size="lg">{{ $editingId ? 'Edit project' : 'New project' }}</flux:heading>
                <flux:subheading>Project codes are immutable. Status changes are validated against allowed transitions.</flux:subheading>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:select wire:model="form.department_id" label="Department (optional)">
                    <option value="">No department</option>
                    @foreach ($departments as $department)
                        <option value="{{ $department->id }}">{{ $department->name }}</option>
                    @endforeach
                </flux:select>

                <flux:select wire:model="form.cost_center_id" label="Cost centre (optional)">
                    <option value="">No cost centre</option>
                    @foreach ($costCenters as $costCenter)
                        <option value="{{ $costCenter->id }}">{{ $costCenter->name }}</option>
                    @endforeach
                </flux:select>

                <flux:input wire:model="form.code" label="Code" placeholder="e.g. PRJ-2026-001" :disabled="$editingId !== null" required />
                <flux:input wire:model="form.name" label="Name" placeholder="e.g. Substation Upgrade" required />

                <flux:select wire:model="form.manager_id" label="Project manager">
                    <option value="">Unassigned</option>
                    @foreach ($managers as $manager)
                        <option value="{{ $manager->id }}">{{ $manager->name }}</option>
                    @endforeach
                </flux:select>

                <flux:select wire:model="form.status" label="Status">
                    @foreach ($statusOptions as $status)
                        <option value="{{ $status->value }}">{{ $status->label() }}</option>
                    @endforeach
                </flux:select>

                <flux:input wire:model="form.start_date" type="date" label="Start date" />
                <flux:input wire:model="form.end_date" type="date" label="End date" />

                <flux:input wire:model="form.budget_amount" label="Budget amount" placeholder="0.00" />
                <flux:input wire:model="form.currency" label="Currency" placeholder="NGN" maxlength="3" />
            </div>

            <flux:textarea wire:model="form.description" label="Description" rows="3" />
            <flux:switch wire:model="form.is_active" label="Active" description="Inactive projects cannot receive new stock issues." />

            <div class="flex justify-end gap-2 pt-2">
                <flux:button type="button" variant="ghost" wire:click="$set('showForm', false)">Cancel</flux:button>
                <flux:button type="submit" variant="primary">{{ $editingId ? 'Save changes' : 'Create project' }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal wire:model="showStatusModal" class="md:w-96">
        <form wire:submit="changeStatus" class="space-y-4">
            <div>
                <flux:heading size="lg">Change project status</flux:heading>
                <flux:subheading>{{ $statusProjectName }}</flux:subheading>
            </div>

            @if (count($allowedStatuses) > 0)
                <flux:select wire:model="newStatus" label="New status" required>
                    @foreach ($statusOptions as $status)
                        @if (in_array($status->value, $allowedStatuses, true))
                            <option value="{{ $status->value }}">{{ $status->label() }}</option>
                        @endif
                    @endforeach
                </flux:select>
            @else
                <flux:callout variant="warning">
                    <flux:callout.text>This project is in a terminal state and cannot change status.</flux:callout.text>
                </flux:callout>
            @endif

            <div class="flex justify-end gap-2">
                <flux:button type="button" variant="ghost" wire:click="$set('showStatusModal', false)">Cancel</flux:button>
                <flux:button type="submit" variant="primary" :disabled="count($allowedStatuses) === 0">Update status</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal wire:model="showMembersModal" class="md:w-[36rem]">
        <div class="space-y-4">
            <div>
                <flux:heading size="lg">Project members</flux:heading>
                <flux:subheading>{{ $membersProjectName }}</flux:subheading>
            </div>

            <form wire:submit="addMember" class="flex flex-wrap items-end gap-3">
                <flux:select wire:model="memberUserId" label="User" class="min-w-48 flex-1">
                    <option value="">Select a user</option>
                    @foreach ($managers as $candidate)
                        <option value="{{ $candidate->id }}">{{ $candidate->name }}</option>
                    @endforeach
                </flux:select>
                <flux:input wire:model="memberRole" label="Role" placeholder="member" class="w-40" />
                <flux:button type="submit" variant="primary">Add</flux:button>
            </form>

            <div class="divide-y divide-zinc-100 dark:divide-zinc-700">
                @forelse ($members as $member)
                    <div class="flex items-center justify-between gap-3 py-2">
                        <div>
                            <div class="text-sm font-medium text-zinc-800 dark:text-zinc-100">{{ $member->user?->name }}</div>
                            <div class="text-xs text-zinc-500 dark:text-zinc-400">
                                {{ $member->role }} &middot; joined {{ $member->joined_at?->format('d M Y') }}
                            </div>
                        </div>
                        <flux:button size="sm" variant="ghost" icon="x-mark" aria-label="Remove member" wire:click="removeMember({{ $member->id }})" />
                    </div>
                @empty
                    <p class="py-4 text-sm text-zinc-500 dark:text-zinc-400">No active members yet.</p>
                @endforelse
            </div>

            <div class="flex justify-end">
                <flux:button type="button" variant="ghost" wire:click="$set('showMembersModal', false)">Done</flux:button>
            </div>
        </div>
    </flux:modal>

    <flux:modal wire:model="showDeleteModal" class="md:w-96">
        <div class="space-y-4">
            <div>
                <flux:heading size="lg">Delete project</flux:heading>
                <flux:subheading>Active projects must be completed or cancelled before deletion.</flux:subheading>
            </div>
            <p class="text-sm text-zinc-600 dark:text-zinc-300">
                Delete <span class="font-semibold">{{ $deletingName }}</span>?
            </p>
            <div class="flex justify-end gap-2">
                <flux:button type="button" variant="ghost" wire:click="$set('showDeleteModal', false)">Cancel</flux:button>
                <flux:button type="button" variant="danger" wire:click="delete">Delete project</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
