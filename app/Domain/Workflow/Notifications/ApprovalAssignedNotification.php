<?php

namespace App\Domain\Workflow\Notifications;

use App\Domain\Workflow\Models\ApprovalRequest;
use App\Domain\Workflow\Models\ApprovalRequestStage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ApprovalAssignedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public ApprovalRequest $request,
        public ApprovalRequestStage $stage,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'approval_assigned',
            'approval_request_id' => $this->request->id,
            'approval_request_stage_id' => $this->stage->id,
            'document_type' => $this->request->document_type,
            'document_id' => $this->request->document_id,
            'stage' => $this->stage->stage?->name ?? "Stage {$this->stage->sequence}",
            'due_at' => $this->stage->sla_due_at?->toIso8601String(),
            'message' => 'A document is awaiting your approval.',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Approval required: '.str_replace('_', ' ', $this->request->document_type))
            ->line('A document is awaiting your approval.')
            ->line('Document: '.str_replace('_', ' ', $this->request->document_type).' #'.$this->request->document_id)
            ->line('Stage: '.($this->stage->stage?->name ?? "Stage {$this->stage->sequence}"))
            ->action('Review approval', url('/approvals/'.$this->request->id));
    }
}
