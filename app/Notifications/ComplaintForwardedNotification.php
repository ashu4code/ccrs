<?php

namespace App\Notifications;

use App\Models\Complaint;
use App\Models\User;
use Illuminate\Notifications\Notification;

class ComplaintForwardedNotification extends Notification
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
    public function toDatabase(User $notifiable): array
    {
        return [
            'title' => 'Complaint escalated to you',
            'message' => sprintf(
                'Complaint %s — %s was forwarded to you for adjudication.',
                $this->complaint->ticket_number,
                $this->complaint->subject,
            ),
            'complaint_id' => $this->complaint->id,
            'ticket_number' => $this->complaint->ticket_number,
        ];
    }
}
