<?php

namespace App\Notifications;

use App\Models\Complaint;
use App\Models\Consumer;
use Illuminate\Notifications\Notification;

class ComplaintEscalatedNotification extends Notification
{
    public function __construct(public Complaint $complaint) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the database representation of the notification.
     */
    public function toDatabase(Consumer $notifiable): array
    {
        return [
            'title' => 'Complaint escalated for adjudication',
            'message' => sprintf(
                'Complaint %s — %s was escalated to the Competent Authority. We will notify you when it is resolved.',
                $this->complaint->ticket_number,
                $this->complaint->subject,
            ),
            'complaint_id' => $this->complaint->id,
            'ticket_number' => $this->complaint->ticket_number,
        ];
    }
}
