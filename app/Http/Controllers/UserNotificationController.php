<?php

namespace App\Http\Controllers;

use App\Models\Complaint;
use App\Models\Consumer;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class UserNotificationController extends Controller
{
    /**
     * Mark a notification read and route the recipient to the complaint it
     * refers to. Officials may open complaints they hold an assignment on
     * (regional manager or competent authority); consumers only their own.
     */
    public function open(Request $request, string $notification): RedirectResponse
    {
        $user = $request->user() ?? $request->user('consumer');

        abort_unless($user instanceof User || $user instanceof Consumer, 403);

        $databaseNotification = $user->notifications()->findOrFail($notification);

        $complaintQuery = Complaint::query()->whereKey($databaseNotification->data['complaint_id'] ?? null);

        if ($user instanceof Consumer) {
            $complaint = $complaintQuery->where('consumer_id', $user->id)->firstOrFail();
        } else {
            $complaint = $complaintQuery
                ->where(function (Builder $query) use ($user): void {
                    $query->where('regional_manager_id', $user->id)
                        ->orWhere('competent_authority_id', $user->id);
                })
                ->firstOrFail();
        }

        $databaseNotification->markAsRead();

        return redirect()->route('complaints.track', $complaint);
    }
}
