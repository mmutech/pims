<?php

namespace App\Domain\Workflow\Notifications;

use App\Domain\Workflow\Models\ApprovalRequest;
use App\Enums\ApprovalStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ApprovalDecisionNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public ApprovalRequest $request,
        public ApprovalStatus $status,
        public ?string $comment = null,
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
            'type' => 'approval_decision',
            'approval_request_id' => $this->request->id,
            'document_type' => $this->request->document_type,
            'document_id' => $this->request->document_id,
            'status' => $this->status->value,
            'comment' => $this->comment,
            'message' => 'Approval request '.$this->status->label().'.',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Approval request '.$this->status->label())
            ->line('Your approval request has been '.$this->status->value.'.')
            ->when($this->comment !== null, fn (MailMessage $mail) => $mail->line('Comment: '.$this->comment))
            ->action('View document history', url('/approvals/history'));
    }
}
