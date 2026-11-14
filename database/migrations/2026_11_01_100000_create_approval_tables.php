<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_flows', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('document_type', 50)->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('approval_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('approval_flow_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('sequence');
            $table->string('name', 150);
            $table->string('approval_mode', 30)->default('any_one');
            $table->unsignedSmallInteger('required_approvals')->default(1);
            $table->unsignedInteger('sla_hours')->nullable();
            $table->decimal('amount_min', 18, 2)->nullable();
            $table->decimal('amount_max', 18, 2)->nullable();
            $table->boolean('prevent_self_approval')->default(true);
            $table->boolean('is_final')->default(false);
            $table->foreignId('escalation_role_id')->nullable()->constrained('roles')->nullOnDelete();
            $table->foreignId('escalation_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['approval_flow_id', 'sequence']);
        });

        Schema::create('approval_stage_conditions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('approval_stage_id')->constrained()->cascadeOnDelete();
            $table->string('field', 50);
            $table->string('operator', 30);
            $table->json('value')->nullable();
            $table->unsignedSmallInteger('logical_group')->default(1);
            $table->timestamps();

            $table->index(['approval_stage_id', 'logical_group'], 'approval_stage_conditions_stage_group_index');
        });

        Schema::create('approval_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('approval_stage_id')->constrained()->cascadeOnDelete();
            $table->string('assignee_type', 30);
            $table->foreignId('role_id')->nullable()->constrained('roles')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->boolean('is_primary')->default(true);
            $table->timestamps();

            $table->index(['approval_stage_id', 'assignee_type']);
        });

        Schema::create('approval_requests', function (Blueprint $table) {
            $table->id();
            $table->string('document_type', 50);
            $table->unsignedBigInteger('document_id');
            $table->foreignId('approval_flow_id')->constrained()->restrictOnDelete();
            $table->string('status', 20)->default('pending')->index();
            $table->unsignedSmallInteger('current_stage_sequence')->nullable();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('requested_at');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('active_key', 120)->nullable()->unique();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['document_type', 'document_id']);
            $table->index(['requested_by', 'status']);
        });

        Schema::create('approval_request_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('approval_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('approval_stage_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('sequence');
            $table->string('name', 150);
            $table->string('approval_mode', 30)->default('any_one');
            $table->boolean('prevent_self_approval')->default(true);
            $table->boolean('is_final')->default(false);
            $table->string('status', 20)->default('pending')->index();
            $table->unsignedSmallInteger('required_approvals')->default(1);
            $table->unsignedSmallInteger('received_approvals')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('sla_due_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('blocked_reason', 255)->nullable();
            $table->timestamps();

            $table->unique(['approval_request_id', 'sequence'], 'approval_request_stages_request_sequence_unique');
            $table->index(['status', 'sla_due_at']);
        });

        Schema::create('approval_stage_assignees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('approval_request_stage_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('status', 20)->default('pending')->index();
            $table->boolean('is_delegated')->default(false);
            $table->foreignId('delegated_from_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('acted_at')->nullable();
            $table->timestamps();

            $table->unique(['approval_request_stage_id', 'user_id'], 'approval_stage_assignees_stage_user_unique');
        });

        Schema::create('approval_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('approval_request_id')->constrained()->restrictOnDelete();
            $table->foreignId('approval_request_stage_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 20);
            $table->text('comment')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('acted_at');
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('approval_delegations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delegator_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('delegate_id')->constrained('users')->cascadeOnDelete();
            $table->string('document_type', 50)->nullable();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->text('reason')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['delegator_id', 'delegate_id', 'is_active'], 'approval_delegations_delegator_delegate_active_index');
            $table->index(['starts_at', 'ends_at']);
        });

        Schema::create('approval_escalations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('approval_request_stage_id')->constrained()->cascadeOnDelete();
            $table->foreignId('escalated_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason', 255);
            $table->timestamp('escalated_at');
            $table->timestamp('resolved_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('approval_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('approval_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('approval_request_stage_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 50);
            $table->string('dedupe_key', 191)->unique();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'read_at']);
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('approval_notifications');
        Schema::dropIfExists('approval_escalations');
        Schema::dropIfExists('approval_delegations');
        Schema::dropIfExists('approval_actions');
        Schema::dropIfExists('approval_stage_assignees');
        Schema::dropIfExists('approval_request_stages');
        Schema::dropIfExists('approval_requests');
        Schema::dropIfExists('approval_assignments');
        Schema::dropIfExists('approval_stage_conditions');
        Schema::dropIfExists('approval_stages');
        Schema::dropIfExists('approval_flows');
    }
};
