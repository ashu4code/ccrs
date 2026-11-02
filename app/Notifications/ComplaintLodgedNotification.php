<?php

namespace App\Notifications;

use App\Models\Complaint;
use App\Models\User;
use Illuminate\Notifications\Notification;

class ComplaintLodgedNotification extends Notification
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
            'title' => 'New complaint lodged',
            'message' => sprintf(
                'Complaint %s — %s was assigned to you for review.',
                $this->complaint->ticket_number,
                $this->complaint->subject,
            ),
            'complaint_id' => $this->complaint->id,
            'ticket_number' => $this->complaint->ticket_number,
        ];
    }
}
