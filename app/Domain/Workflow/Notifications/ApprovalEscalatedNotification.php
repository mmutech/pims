<?php

namespace App\Domain\Workflow\Notifications;

use App\Domain\Workflow\Models\ApprovalEscalation;
use App\Domain\Workflow\Models\ApprovalRequest;
use App\Domain\Workflow\Models\ApprovalRequestStage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ApprovalEscalatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public ApprovalRequest $request,
        public ApprovalRequestStage $stage,
        public ApprovalEscalation $escalation,
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
            'type' => 'approval_escalated',
            'approval_request_id' => $this->request->id,
            'approval_request_stage_id' => $this->stage->id,
            'escalation_id' => $this->escalation->id,
            'reason' => $this->escalation->reason,
            'message' => 'An approval stage has breached its SLA.',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Approval SLA breached')
            ->line('An approval stage has breached its SLA and has been escalated.')
            ->line('Reason: '.$this->escalation->reason)
            ->action('Review approval', url('/approvals/'.$this->request->id));
    }
}
