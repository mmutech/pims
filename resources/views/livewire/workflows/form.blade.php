<div class="space-y-6">
    <x-page-header :title="$isEditing ? 'Edit Approval Workflow' : 'New Approval Workflow'" description="Stages, conditions, and assignees are evaluated at submission time. Pending requests keep their original snapshot.">
        <x-slot name="actions">
            <flux:button variant="ghost" icon="arrow-left" :href="route('workflows.index')" wire:navigate>Back to workflows</flux:button>
        </x-slot>
    </x-page-header>

    <x-domain-feedback :message="$feedback" :type="$feedbackType" />

    <form wire:submit="save" class="space-y-6">
        <flux:card>
            <flux:heading size="lg">Workflow details</flux:heading>
            <flux:subheading>One active workflow per document type. The document type is the key modules use when submitting for approval.</flux:subheading>

            <div class="mt-5 grid gap-4 md:grid-cols-2">
                <flux:input wire:model="form.name" label="Name" placeholder="e.g. Purchase requisition approval" required />
                <flux:input wire:model="form.document_type" label="Document type" placeholder="purchase_requisition" description="Lowercase letters, numbers, and underscores." required />
                <flux:input wire:model="form.description" label="Description" placeholder="When this workflow applies" class="md:col-span-2" />
                <flux:switch wire:model="form.is_active" label="Active" description="Only active workflows are used for new submissions." />
            </div>
        </flux:card>

        <div class="space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <flux:heading size="lg">Stages</flux:heading>
                    <flux:subheading>Stages run in order. Use any-one for a single decision, all for every assignee, or split sequential approvers into separate stages.</flux:subheading>
                </div>
                <flux:button type="button" variant="primary" icon="plus" wire:click="addStage">Add stage</flux:button>
            </div>

            @foreach ($form['stages'] as $stageIndex => $stage)
                <flux:card wire:key="workflow-stage-{{ $stageIndex }}" class="space-y-5">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <span class="flex size-8 items-center justify-center rounded-full bg-navy-900 text-sm font-semibold text-white">{{ $stageIndex + 1 }}</span>
                            <div>
                                <flux:heading size="lg">Stage {{ $stageIndex + 1 }}</flux:heading>
                                <flux:subheading>{{ $stage['name'] !== '' ? $stage['name'] : 'Unnamed stage' }}</flux:subheading>
                            </div>
                        </div>
                        <div class="flex flex-wrap items-center gap-1">
                            <flux:button type="button" size="sm" variant="ghost" icon="arrow-up" wire:click="moveStage({{ $stageIndex }}, -1)" :disabled="$stageIndex === 0" aria-label="Move stage up" />
                            <flux:button type="button" size="sm" variant="ghost" icon="arrow-down" wire:click="moveStage({{ $stageIndex }}, 1)" :disabled="$stageIndex === count($form['stages']) - 1" aria-label="Move stage down" />
                            <flux:button type="button" size="sm" variant="ghost" icon="trash" wire:click="removeStage({{ $stageIndex }})" wire:confirm="Remove this stage?" aria-label="Remove stage" />
                        </div>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <flux:input wire:model="form.stages.{{ $stageIndex }}.name" label="Stage name" placeholder="e.g. Procurement manager review" required />
                        <flux:error :name="'form.stages.'.$stageIndex.'.name'" />
                        <flux:select wire:model.live="form.stages.{{ $stageIndex }}.approval_mode" label="Approval mode">
                            @foreach ($modes as $mode)
                                <option value="{{ $mode->value }}">{{ $mode->label() }}</option>
                            @endforeach
                        </flux:select>
                        <flux:input wire:model="form.stages.{{ $stageIndex }}.required_approvals" type="number" min="1" max="100" label="Required approvals" description="Ignored for any-one (always one)." />
                        <flux:input wire:model="form.stages.{{ $stageIndex }}.sla_hours" type="number" min="1" max="8760" label="SLA (hours)" placeholder="Optional" />
                        <flux:input wire:model="form.stages.{{ $stageIndex }}.amount_min" type="number" step="0.01" min="0" label="Amount from" placeholder="No lower bound" />
                        <flux:input wire:model="form.stages.{{ $stageIndex }}.amount_max" type="number" step="0.01" min="0" label="Amount below" placeholder="Exclusive upper bound" />
                        <flux:error :name="'form.stages.'.$stageIndex.'.amount_max'" />
                        <div class="space-y-3 md:col-span-2">
                            <flux:switch wire:model="form.stages.{{ $stageIndex }}.prevent_self_approval" label="Prevent self-approval" description="Excludes the document creator and last editor from this stage." />
                            <flux:switch wire:model="form.stages.{{ $stageIndex }}.is_final" label="Final stage" description="Approval completes the request immediately, skipping later stages." />
                        </div>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <flux:select wire:model="form.stages.{{ $stageIndex }}.escalation_role_id" label="Escalate to role">
                            <option value="">No role escalation</option>
                            @foreach ($roles as $role)
                                <option value="{{ $role->id }}">{{ $role->name }}</option>
                            @endforeach
                        </flux:select>
                        <flux:select wire:model="form.stages.{{ $stageIndex }}.escalation_user_id" label="Escalate to user" searchable>
                            <option value="">No user escalation</option>
                            @foreach ($users as $user)
                                <option value="{{ $user->id }}">{{ $user->name }}</option>
                            @endforeach
                        </flux:select>
                    </div>

                    <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <div class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">Conditions</div>
                                <div class="text-xs text-zinc-500 dark:text-zinc-400">Groups are ANDed; conditions inside a group are ORed. Leave empty to always apply.</div>
                            </div>
                            <flux:button type="button" size="sm" variant="ghost" icon="plus" wire:click="addCondition({{ $stageIndex }})">Add condition</flux:button>
                        </div>

                        @forelse ($stage['conditions'] as $conditionIndex => $condition)
                            <div class="mt-3 grid gap-3 sm:grid-cols-12" wire:key="condition-{{ $stageIndex }}-{{ $conditionIndex }}">
                                <flux:input wire:model="form.stages.{{ $stageIndex }}.conditions.{{ $conditionIndex }}.field" label="Field" list="approval-condition-fields" placeholder="amount" class="sm:col-span-4" />
                                <flux:select wire:model="form.stages.{{ $stageIndex }}.conditions.{{ $conditionIndex }}.operator" label="Operator" class="sm:col-span-3">
                                    @foreach ($operators as $operator)
                                        <option value="{{ $operator->value }}">{{ $operator->label() }}</option>
                                    @endforeach
                                </flux:select>
                                <flux:input wire:model="form.stages.{{ $stageIndex }}.conditions.{{ $conditionIndex }}.value" label="Value" placeholder="Comma-separate lists and ranges" class="sm:col-span-3" />
                                <flux:input wire:model="form.stages.{{ $stageIndex }}.conditions.{{ $conditionIndex }}.logical_group" type="number" min="1" label="Group" class="sm:col-span-1" />
                                <div class="flex items-end sm:col-span-1">
                                    <flux:button type="button" variant="ghost" icon="trash" wire:click="removeCondition({{ $stageIndex }}, {{ $conditionIndex }})" aria-label="Remove condition" />
                                </div>
                                <flux:error :name="'form.stages.'.$stageIndex.'.conditions.'.$conditionIndex.'.field'" class="sm:col-span-4" />
                                <flux:error :name="'form.stages.'.$stageIndex.'.conditions.'.$conditionIndex.'.value'" class="sm:col-span-12" />
                            </div>
                        @empty
                            <p class="mt-3 text-xs text-zinc-500 dark:text-zinc-400">No conditions — this stage applies to every matching amount.</p>
                        @endforelse
                    </div>

                    <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <div class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">Assignees</div>
                                <div class="text-xs text-zinc-500 dark:text-zinc-400">Role assignees are scoped to the document's business unit. Manager assignees are resolved from the document.</div>
                            </div>
                            <flux:button type="button" size="sm" variant="ghost" icon="plus" wire:click="addAssignment({{ $stageIndex }})">Add assignee</flux:button>
                        </div>

                        @forelse ($stage['assignments'] as $assignmentIndex => $assignment)
                            <div class="mt-3 grid gap-3 sm:grid-cols-12" wire:key="assignment-{{ $stageIndex }}-{{ $assignmentIndex }}">
                                <flux:select wire:model.live="form.stages.{{ $stageIndex }}.assignments.{{ $assignmentIndex }}.assignee_type" label="Assignee type" class="sm:col-span-4">
                                    @foreach ($assigneeTypes as $assigneeType)
                                        <option value="{{ $assigneeType->value }}">{{ $assigneeType->label() }}</option>
                                    @endforeach
                                </flux:select>
                                @if ($assignment['assignee_type'] === 'role')
                                    <flux:select wire:model="form.stages.{{ $stageIndex }}.assignments.{{ $assignmentIndex }}.role_id" label="Role" class="sm:col-span-6" searchable>
                                        <option value="">Select a role</option>
                                        @foreach ($roles as $role)
                                            <option value="{{ $role->id }}">{{ $role->name }}</option>
                                        @endforeach
                                    </flux:select>
                                @elseif ($assignment['assignee_type'] === 'user')
                                    <flux:select wire:model="form.stages.{{ $stageIndex }}.assignments.{{ $assignmentIndex }}.user_id" label="User" class="sm:col-span-6" searchable>
                                        <option value="">Select a user</option>
                                        @foreach ($users as $user)
                                            <option value="{{ $user->id }}">{{ $user->name }}</option>
                                        @endforeach
                                    </flux:select>
                                @else
                                    <div class="flex items-center text-xs text-zinc-500 dark:text-zinc-400 sm:col-span-6">
                                        Resolved from the document's {{ str($assignment['assignee_type'])->replace('_', ' ') }} at submission time.
                                    </div>
                                @endif
                                <div class="flex items-end sm:col-span-2">
                                    <flux:button type="button" variant="ghost" icon="trash" wire:click="removeAssignment({{ $stageIndex }}, {{ $assignmentIndex }})" aria-label="Remove assignee" />
                                </div>
                                <flux:error :name="'form.stages.'.$stageIndex.'.assignments.'.$assignmentIndex.'.role_id'" class="sm:col-span-4" />
                                <flux:error :name="'form.stages.'.$stageIndex.'.assignments.'.$assignmentIndex.'.user_id'" class="sm:col-span-6" />
                                <flux:error :name="'form.stages.'.$stageIndex.'.assignments.'.$assignmentIndex.'.assignee_type'" class="sm:col-span-12" />
                            </div>
                        @empty
                            <p class="mt-3 text-xs text-red-600 dark:text-red-400">Every stage needs at least one assignee.</p>
                        @endforelse
                    </div>
                </flux:card>
            @endforeach
        </div>

        <div class="flex flex-wrap items-center justify-end gap-2">
            <flux:button type="button" variant="ghost" :href="route('workflows.index')" wire:navigate>Cancel</flux:button>
            <flux:button type="submit" variant="primary" icon="check">{{ $isEditing ? 'Save workflow' : 'Create workflow' }}</flux:button>
        </div>
    </form>

    <datalist id="approval-condition-fields">
        @foreach ($conditionFields as $field)
            <option value="{{ $field }}"></option>
        @endforeach
    </datalist>
</div>
