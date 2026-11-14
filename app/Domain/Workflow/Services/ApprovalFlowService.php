<?php

namespace App\Domain\Workflow\Services;

use App\Domain\Workflow\Models\ApprovalFlow;
use App\Domain\Workflow\Models\ApprovalRequest;
use App\Enums\ApprovalAssigneeType;
use App\Enums\ApprovalConditionOperator;
use App\Enums\ApprovalMode;
use App\Enums\ApprovalStatus;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class ApprovalFlowService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $actor): ApprovalFlow
    {
        $validated = $this->validateFlow($data);

        return DB::transaction(function () use ($validated, $actor): ApprovalFlow {
            if (ApprovalFlow::query()->where('document_type', $validated['document_type'])->exists()) {
                throw ValidationException::withMessages([
                    'document_type' => 'An approval flow already exists for this document type.',
                ]);
            }

            $flow = ApprovalFlow::create([
                'name' => $validated['name'],
                'document_type' => $validated['document_type'],
                'description' => $validated['description'] ?? null,
                'is_active' => (bool) ($validated['is_active'] ?? false),
                'version' => 1,
                'created_by' => $actor->id,
            ]);

            $this->syncStages($flow, $validated['stages'] ?? []);

            return $flow->refresh()->load('stages.conditions', 'stages.assignments');
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(ApprovalFlow $flow, array $data, User $actor): ApprovalFlow
    {
        $validated = $this->validateFlow($data, $flow);

        return DB::transaction(function () use ($flow, $validated): ApprovalFlow {
            $duplicate = ApprovalFlow::query()
                ->where('document_type', $validated['document_type'])
                ->whereKeyNot($flow->id)
                ->exists();

            if ($duplicate) {
                throw ValidationException::withMessages([
                    'form.document_type' => 'An approval flow already exists for this document type.',
                ]);
            }

            $flow->update([
                'name' => $validated['name'],
                'document_type' => $validated['document_type'],
                'description' => $validated['description'] ?? null,
                'is_active' => (bool) ($validated['is_active'] ?? false),
                'version' => $flow->version + 1,
            ]);

            $flow->stages()->delete();
            $this->syncStages($flow, $validated['stages'] ?? []);

            return $flow->refresh()->load('stages.conditions', 'stages.assignments');
        });
    }

    public function activate(ApprovalFlow $flow): ApprovalFlow
    {
        if ($flow->stages()->count() === 0) {
            throw new DomainException('A flow must have at least one stage before it can be activated.');
        }

        $flow->update(['is_active' => true, 'version' => $flow->version + 1]);

        return $flow->refresh();
    }

    public function deactivate(ApprovalFlow $flow): ApprovalFlow
    {
        $flow->update(['is_active' => false, 'version' => $flow->version + 1]);

        return $flow->refresh();
    }

    public function delete(ApprovalFlow $flow): void
    {
        $pending = ApprovalRequest::query()
            ->where('approval_flow_id', $flow->id)
            ->where('status', ApprovalStatus::Pending->value)
            ->exists();

        if ($pending) {
            throw new DomainException('This flow has pending approval requests and cannot be deleted.');
        }

        $flow->delete();
    }

    /**
     * @param  list<array<string, mixed>>  $stages
     */
    private function syncStages(ApprovalFlow $flow, array $stages): void
    {
        foreach (array_values($stages) as $index => $stageData) {
            $sequence = (int) ($stageData['sequence'] ?? ($index + 1));

            $stage = $flow->stages()->create([
                'sequence' => $sequence,
                'name' => $stageData['name'],
                'approval_mode' => $stageData['approval_mode'] ?? ApprovalMode::AnyOne->value,
                'required_approvals' => (int) ($stageData['required_approvals'] ?? 1),
                'sla_hours' => isset($stageData['sla_hours']) && $stageData['sla_hours'] !== ''
                    ? (int) $stageData['sla_hours']
                    : null,
                'amount_min' => $stageData['amount_min'] ?? null,
                'amount_max' => $stageData['amount_max'] ?? null,
                'prevent_self_approval' => (bool) ($stageData['prevent_self_approval'] ?? true),
                'is_final' => (bool) ($stageData['is_final'] ?? false),
                'escalation_role_id' => $stageData['escalation_role_id'] ?? null,
                'escalation_user_id' => $stageData['escalation_user_id'] ?? null,
            ]);

            foreach ($stageData['conditions'] ?? [] as $condition) {
                $stage->conditions()->create([
                    'field' => $condition['field'],
                    'operator' => $condition['operator'],
                    'value' => $condition['value'] ?? null,
                    'logical_group' => (int) ($condition['logical_group'] ?? 1),
                ]);
            }

            foreach ($stageData['assignments'] ?? [] as $assignment) {
                $stage->assignments()->create([
                    'assignee_type' => $assignment['assignee_type'],
                    'role_id' => $assignment['role_id'] ?? null,
                    'user_id' => $assignment['user_id'] ?? null,
                    'department_id' => $assignment['department_id'] ?? null,
                    'project_id' => $assignment['project_id'] ?? null,
                    'warehouse_id' => $assignment['warehouse_id'] ?? null,
                    'is_primary' => (bool) ($assignment['is_primary'] ?? true),
                ]);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function validateFlow(array $data, ?ApprovalFlow $flow = null): array
    {
        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'max:150'],
            'document_type' => ['required', 'string', 'max:50', 'regex:/^[a-z0-9_]+$/'],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
            'stages' => ['required', 'array', 'min:1'],
            'stages.*.name' => ['required', 'string', 'max:150'],
            'stages.*.sequence' => ['nullable', 'integer', 'min:1'],
            'stages.*.approval_mode' => ['required', 'in:'.implode(',', ApprovalMode::values())],
            'stages.*.required_approvals' => ['nullable', 'integer', 'min:1', 'max:100'],
            'stages.*.sla_hours' => ['nullable', 'integer', 'min:1', 'max:8760'],
            'stages.*.amount_min' => ['nullable', 'numeric'],
            'stages.*.amount_max' => ['nullable', 'numeric'],
            'stages.*.prevent_self_approval' => ['boolean'],
            'stages.*.is_final' => ['boolean'],
            'stages.*.escalation_role_id' => ['nullable', 'integer', 'exists:roles,id'],
            'stages.*.escalation_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'stages.*.conditions' => ['nullable', 'array'],
            'stages.*.conditions.*.field' => ['required', 'string', 'max:50'],
            'stages.*.conditions.*.operator' => ['required', 'in:'.implode(',', ApprovalConditionOperator::values())],
            'stages.*.conditions.*.value' => ['nullable'],
            'stages.*.conditions.*.logical_group' => ['nullable', 'integer', 'min:1'],
            'stages.*.assignments' => ['required', 'array', 'min:1'],
            'stages.*.assignments.*.assignee_type' => ['required', 'in:'.implode(',', ApprovalAssigneeType::values())],
            'stages.*.assignments.*.role_id' => ['nullable', 'integer', 'exists:roles,id'],
            'stages.*.assignments.*.user_id' => ['nullable', 'integer', 'exists:users,id'],
            'stages.*.assignments.*.department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'stages.*.assignments.*.project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'stages.*.assignments.*.warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'stages.*.assignments.*.is_primary' => ['boolean'],
        ], [
            'stages.*.assignments.required' => 'Every stage needs at least one assignee.',
        ]);

        $validator->after(function ($validator) use ($data): void {
            foreach ($data['stages'] ?? [] as $index => $stage) {
                $min = $stage['amount_min'] ?? null;
                $max = $stage['amount_max'] ?? null;

                if ($min !== null && $min !== '' && $max !== null && $max !== '' && bccomp((string) $min, (string) $max, 2) >= 0) {
                    $validator->errors()->add("stages.{$index}.amount_max", 'The upper band must be greater than the lower band.');
                }

                foreach ($stage['conditions'] ?? [] as $conditionIndex => $condition) {
                    $operator = ApprovalConditionOperator::tryFrom((string) ($condition['operator'] ?? ''));

                    if ($operator === null) {
                        $validator->errors()->add(
                            "stages.{$index}.conditions.{$conditionIndex}.operator",
                            'The condition operator is invalid.',
                        );
                    }
                }

                foreach ($stage['assignments'] ?? [] as $assignmentIndex => $assignment) {
                    $type = ApprovalAssigneeType::tryFrom((string) ($assignment['assignee_type'] ?? ''));

                    if ($type === null) {
                        continue;
                    }

                    if ($type->requiresRole() && blank($assignment['role_id'] ?? null)) {
                        $validator->errors()->add(
                            "stages.{$index}.assignments.{$assignmentIndex}.role_id",
                            'Select a role for this assignment.',
                        );
                    }

                    if ($type->requiresUser() && blank($assignment['user_id'] ?? null)) {
                        $validator->errors()->add(
                            "stages.{$index}.assignments.{$assignmentIndex}.user_id",
                            'Select a user for this assignment.',
                        );
                    }
                }
            }
        });

        return $validator->validate();
    }
}
