<?php

namespace App\Livewire\Workflows;

use App\Domain\Workflow\Models\ApprovalFlow;
use App\Domain\Workflow\Models\ApprovalStageCondition;
use App\Domain\Workflow\Services\ApprovalFlowService;
use App\Enums\ApprovalAssigneeType;
use App\Enums\ApprovalConditionOperator;
use App\Enums\ApprovalMode;
use App\Enums\UserStatus;
use App\Livewire\Concerns\HandlesDomainActions;
use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Spatie\Permission\Models\Role;

#[Layout('layouts.app')]
#[Title('Approval Workflow')]
class Form extends Component
{
    use HandlesDomainActions;

    public ?int $flowId = null;

    /** @var array<string, mixed> */
    public array $form = [];

    public function mount(?ApprovalFlow $flow = null): void
    {
        if ($flow !== null && $flow->exists) {
            $this->authorize('update', $flow);
            $this->flowId = $flow->id;
            $this->form = $this->formFromFlow($flow);

            return;
        }

        $this->authorize('create', ApprovalFlow::class);
        $this->resetForm();
    }

    public function resetForm(): void
    {
        $this->flowId = null;
        $this->form = [
            'name' => '',
            'document_type' => '',
            'description' => '',
            'is_active' => false,
            'stages' => [$this->blankStage()],
        ];
        $this->resetValidation();
    }

    public function addStage(): void
    {
        $this->form['stages'][] = $this->blankStage();
    }

    public function removeStage(int $index): void
    {
        if (count($this->form['stages']) <= 1) {
            $this->error('A workflow needs at least one stage.');

            return;
        }

        array_splice($this->form['stages'], $index, 1);
    }

    public function moveStage(int $index, int $direction): void
    {
        $target = $index + $direction;

        if ($index < 0 || $target < 0 || $target >= count($this->form['stages'])) {
            return;
        }

        $stages = $this->form['stages'];
        [$stages[$index], $stages[$target]] = [$stages[$target], $stages[$index]];
        $this->form['stages'] = array_values($stages);
    }

    public function addCondition(int $stageIndex): void
    {
        $this->form['stages'][$stageIndex]['conditions'][] = $this->blankCondition();
    }

    public function removeCondition(int $stageIndex, int $conditionIndex): void
    {
        array_splice($this->form['stages'][$stageIndex]['conditions'], $conditionIndex, 1);
    }

    public function addAssignment(int $stageIndex): void
    {
        $this->form['stages'][$stageIndex]['assignments'][] = $this->blankAssignment();
    }

    public function removeAssignment(int $stageIndex, int $assignmentIndex): void
    {
        if (count($this->form['stages'][$stageIndex]['assignments']) <= 1) {
            $this->error('Every stage needs at least one assignee.');

            return;
        }

        array_splice($this->form['stages'][$stageIndex]['assignments'], $assignmentIndex, 1);
    }

    public function save(ApprovalFlowService $service)
    {
        $flow = $this->flowId !== null ? ApprovalFlow::findOrFail($this->flowId) : null;

        $flow !== null
            ? $this->authorize('update', $flow)
            : $this->authorize('create', ApprovalFlow::class);

        $this->validate($this->rules());

        $this->validateStructure();

        if ($this->getErrorBag()->isNotEmpty()) {
            return null;
        }

        $payload = $this->payload();

        $duplicate = ApprovalFlow::query()
            ->where('document_type', $payload['document_type'])
            ->when($flow !== null, fn ($query) => $query->whereKeyNot($flow->id))
            ->exists();

        if ($duplicate) {
            $this->addError('form.document_type', 'An approval flow already exists for this document type.');

            return null;
        }

        $saved = false;

        $result = $this->runDomainAction(function () use ($service, $flow, $payload, &$saved): void {
            $flow !== null
                ? $service->update($flow, $payload, auth()->user())
                : $service->create($payload, auth()->user());

            $saved = true;
        }, $flow !== null ? 'Approval workflow updated.' : 'Approval workflow created.');

        if (! $saved) {
            return null;
        }

        session()->flash('status', $flow !== null ? 'Approval workflow updated.' : 'Approval workflow created.');

        return $this->redirectRoute('workflows.index', navigate: true);
    }

    /**
     * @return array<string, mixed>
     */
    private function formFromFlow(ApprovalFlow $flow): array
    {
        $flow->load(['stages.conditions', 'stages.assignments']);

        return [
            'name' => $flow->name,
            'document_type' => $flow->document_type,
            'description' => (string) $flow->description,
            'is_active' => $flow->is_active,
            'stages' => $flow->stages->map(fn ($stage): array => [
                'name' => $stage->name,
                'approval_mode' => $stage->approval_mode->value,
                'required_approvals' => $stage->required_approvals,
                'sla_hours' => $stage->sla_hours,
                'amount_min' => $stage->amount_min !== null ? (string) $stage->amount_min : '',
                'amount_max' => $stage->amount_max !== null ? (string) $stage->amount_max : '',
                'prevent_self_approval' => $stage->prevent_self_approval,
                'is_final' => $stage->is_final,
                'escalation_role_id' => $stage->escalation_role_id,
                'escalation_user_id' => $stage->escalation_user_id,
                'conditions' => $stage->conditions->map(fn (ApprovalStageCondition $condition): array => [
                    'field' => $condition->field,
                    'operator' => $condition->operator->value,
                    'value' => $this->conditionValueToString($condition->value),
                    'logical_group' => $condition->logical_group,
                ])->all(),
                'assignments' => $stage->assignments->map(fn ($assignment): array => [
                    'assignee_type' => $assignment->assignee_type->value,
                    'role_id' => $assignment->role_id,
                    'user_id' => $assignment->user_id,
                ])->all(),
            ])->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function blankStage(): array
    {
        return [
            'name' => '',
            'approval_mode' => ApprovalMode::AnyOne->value,
            'required_approvals' => 1,
            'sla_hours' => '',
            'amount_min' => '',
            'amount_max' => '',
            'prevent_self_approval' => true,
            'is_final' => false,
            'escalation_role_id' => '',
            'escalation_user_id' => '',
            'conditions' => [],
            'assignments' => [$this->blankAssignment()],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function blankCondition(): array
    {
        return [
            'field' => '',
            'operator' => ApprovalConditionOperator::Equals->value,
            'value' => '',
            'logical_group' => 1,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function blankAssignment(): array
    {
        return [
            'assignee_type' => ApprovalAssigneeType::Role->value,
            'role_id' => '',
            'user_id' => '',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(): array
    {
        return [
            'form.name' => ['required', 'string', 'max:150'],
            'form.document_type' => ['required', 'string', 'max:50', 'regex:/^[a-z0-9_]+$/'],
            'form.description' => ['nullable', 'string', 'max:1000'],
            'form.is_active' => ['boolean'],
            'form.stages' => ['required', 'array', 'min:1'],
            'form.stages.*.name' => ['required', 'string', 'max:150'],
            'form.stages.*.approval_mode' => ['required', 'in:'.implode(',', ApprovalMode::values())],
            'form.stages.*.required_approvals' => ['required', 'integer', 'min:1', 'max:100'],
            'form.stages.*.sla_hours' => ['nullable', 'integer', 'min:1', 'max:8760'],
            'form.stages.*.amount_min' => ['nullable', 'numeric', 'min:0'],
            'form.stages.*.amount_max' => ['nullable', 'numeric', 'min:0'],
            'form.stages.*.prevent_self_approval' => ['boolean'],
            'form.stages.*.is_final' => ['boolean'],
            'form.stages.*.escalation_role_id' => ['nullable', 'integer', 'exists:roles,id'],
            'form.stages.*.escalation_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'form.stages.*.conditions.*.field' => ['required', 'string', 'max:50'],
            'form.stages.*.conditions.*.operator' => ['required', 'in:'.implode(',', ApprovalConditionOperator::values())],
            'form.stages.*.conditions.*.value' => ['nullable', 'string', 'max:500'],
            'form.stages.*.conditions.*.logical_group' => ['required', 'integer', 'min:1', 'max:100'],
            'form.stages.*.assignments' => ['required', 'array', 'min:1'],
            'form.stages.*.assignments.*.assignee_type' => [
                'required',
                'in:'.implode(',', array_map(
                    fn (ApprovalAssigneeType $type) => $type->value,
                    array_filter(ApprovalAssigneeType::cases(), fn (ApprovalAssigneeType $type) => $type->isSupported()),
                )),
            ],
            'form.stages.*.assignments.*.role_id' => ['nullable', 'integer', 'exists:roles,id'],
            'form.stages.*.assignments.*.user_id' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }

    private function validateStructure(): void
    {
        foreach ($this->form['stages'] ?? [] as $stageIndex => $stage) {
            $min = $stage['amount_min'] ?? null;
            $max = $stage['amount_max'] ?? null;

            if ($min !== null && $min !== '' && $max !== null && $max !== ''
                && bccomp((string) $min, (string) $max, 2) >= 0) {
                $this->addError(
                    "form.stages.{$stageIndex}.amount_max",
                    'The upper band must be greater than the lower band.',
                );
            }

            foreach ($stage['conditions'] ?? [] as $conditionIndex => $condition) {
                $operator = ApprovalConditionOperator::tryFrom((string) ($condition['operator'] ?? ''));
                $values = $this->normaliseConditionValue($condition['value'] ?? '');

                if ($operator === null) {
                    continue;
                }

                if ($operator->expectsList() && $values === []) {
                    $this->addError(
                        "form.stages.{$stageIndex}.conditions.{$conditionIndex}.value",
                        'Enter one or more comma-separated values.',
                    );
                }

                if ($operator === ApprovalConditionOperator::Between && count($values) !== 2) {
                    $this->addError(
                        "form.stages.{$stageIndex}.conditions.{$conditionIndex}.value",
                        'Enter exactly two comma-separated bounds (min, max).',
                    );
                }
            }

            foreach ($stage['assignments'] ?? [] as $assignmentIndex => $assignment) {
                $type = ApprovalAssigneeType::tryFrom((string) ($assignment['assignee_type'] ?? ''));

                if ($type === null || ! $type->isSupported()) {
                    $this->addError(
                        "form.stages.{$stageIndex}.assignments.{$assignmentIndex}.assignee_type",
                        'Select a supported assignee type.',
                    );

                    continue;
                }

                if ($type->requiresRole() && blank($assignment['role_id'] ?? null)) {
                    $this->addError(
                        "form.stages.{$stageIndex}.assignments.{$assignmentIndex}.role_id",
                        'Select a role for this assignment.',
                    );
                }

                if ($type->requiresUser() && blank($assignment['user_id'] ?? null)) {
                    $this->addError(
                        "form.stages.{$stageIndex}.assignments.{$assignmentIndex}.user_id",
                        'Select a user for this assignment.',
                    );
                }
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        $stages = [];

        foreach (array_values($this->form['stages']) as $index => $stage) {
            $conditions = [];

            foreach ($stage['conditions'] ?? [] as $condition) {
                $conditions[] = [
                    'field' => trim((string) $condition['field']),
                    'operator' => $condition['operator'],
                    'value' => $this->normaliseConditionValue($condition['value'] ?? ''),
                    'logical_group' => (int) ($condition['logical_group'] ?? 1),
                ];
            }

            $assignments = [];

            foreach ($stage['assignments'] ?? [] as $assignment) {
                $type = ApprovalAssigneeType::from((string) $assignment['assignee_type']);

                $assignments[] = [
                    'assignee_type' => $type->value,
                    'role_id' => $type->requiresRole() ? (int) $assignment['role_id'] : null,
                    'user_id' => $type->requiresUser() ? (int) $assignment['user_id'] : null,
                    'is_primary' => true,
                ];
            }

            $stages[] = [
                'sequence' => $index + 1,
                'name' => trim((string) $stage['name']),
                'approval_mode' => $stage['approval_mode'],
                'required_approvals' => (int) $stage['required_approvals'],
                'sla_hours' => ($stage['sla_hours'] ?? '') !== '' ? (int) $stage['sla_hours'] : null,
                'amount_min' => ($stage['amount_min'] ?? '') !== '' ? (string) $stage['amount_min'] : null,
                'amount_max' => ($stage['amount_max'] ?? '') !== '' ? (string) $stage['amount_max'] : null,
                'prevent_self_approval' => (bool) $stage['prevent_self_approval'],
                'is_final' => (bool) $stage['is_final'],
                'escalation_role_id' => ($stage['escalation_role_id'] ?? '') !== '' ? (int) $stage['escalation_role_id'] : null,
                'escalation_user_id' => ($stage['escalation_user_id'] ?? '') !== '' ? (int) $stage['escalation_user_id'] : null,
                'conditions' => $conditions,
                'assignments' => $assignments,
            ];
        }

        return [
            'name' => trim((string) $this->form['name']),
            'document_type' => strtolower(trim((string) $this->form['document_type'])),
            'description' => trim((string) $this->form['description']) !== '' ? trim((string) $this->form['description']) : null,
            'is_active' => (bool) $this->form['is_active'],
            'stages' => $stages,
        ];
    }

    /**
     * @return list<string>
     */
    private function normaliseConditionValue(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_filter(array_map(
                fn ($item) => trim((string) $item),
                $value,
            ), fn (string $item) => $item !== ''));
        }

        $parts = preg_split('/\s*,\s*/', trim((string) $value), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_filter(array_map('trim', $parts), fn (string $item) => $item !== ''));
    }

    private function conditionValueToString(mixed $value): string
    {
        if (is_array($value)) {
            return implode(', ', array_map(fn ($item) => (string) $item, $value));
        }

        return (string) $value;
    }

    public function render()
    {
        $supportedTypes = array_values(array_filter(
            ApprovalAssigneeType::cases(),
            fn (ApprovalAssigneeType $type) => $type->isSupported(),
        ));

        return view('livewire.workflows.form', [
            'modes' => ApprovalMode::cases(),
            'operators' => ApprovalConditionOperator::cases(),
            'assigneeTypes' => $supportedTypes,
            'roles' => Role::query()->orderBy('name')->get(),
            'users' => User::query()
                ->where('status', UserStatus::Active->value)
                ->orderBy('name')
                ->get(['id', 'name']),
            'conditionFields' => [
                'amount',
                'priority',
                'document_number',
                'business_unit_id',
                'department_id',
                'project_id',
                'warehouse_id',
                'item_category_id',
                'supplier_id',
                'requested_by',
                'created_by',
                'updated_by',
            ],
            'isEditing' => $this->flowId !== null,
        ]);
    }
}
