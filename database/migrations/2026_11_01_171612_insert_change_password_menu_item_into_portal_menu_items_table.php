<?php

use App\Models\PortalMenuItem;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Add the consumer sidebar link to the password change page. The link
     * slots between Profile and Help & Contact, tail items shift down one step.
     */
    public function up(): void
    {
        DB::transaction(function (): void {
            if (PortalMenuItem::query()->where('audience', PortalMenuItem::AUDIENCE_CONSUMER)->where('label', 'Change Password')->exists()) {
                return;
            }

            PortalMenuItem::query()
                ->where('audience', PortalMenuItem::AUDIENCE_CONSUMER)
                ->where('sort_order', '>=', 6)
                ->increment('sort_order');

            PortalMenuItem::create([
                'audience' => PortalMenuItem::AUDIENCE_CONSUMER,
                'section' => 'Account',
                'label' => 'Change Password',
                'route_name' => 'password.change',
                'icon' => 'shield',
                'sort_order' => 6,
            ]);
        });
    }

    /**
     * Remove the seeded link and restore the original tail ordering.
     */
    public function down(): void
    {
        DB::transaction(function (): void {
            PortalMenuItem::query()
                ->where('audience', PortalMenuItem::AUDIENCE_CONSUMER)
                ->where('label', 'Change Password')
                ->first()
                ?->delete();

            PortalMenuItem::query()
                ->where('audience', PortalMenuItem::AUDIENCE_CONSUMER)
                ->where('sort_order', '>', 6)
                ->decrement('sort_order');
        });
    }
};
