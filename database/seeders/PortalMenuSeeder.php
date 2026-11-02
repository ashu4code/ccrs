<?php

namespace Database\Seeders;

use App\Models\PortalMenuItem;
use Illuminate\Database\Seeder;

class PortalMenuSeeder extends Seeder
{
    /**
     * Seed the default role-aware sidebar menus. Items without a stored
     * destination render as "coming soon" placeholders until their pages exist.
     */
    public function run(): void
    {
        PortalMenuItem::query()->delete();

        $menus = [
            PortalMenuItem::AUDIENCE_CONSUMER => [
                'Complaints' => [
                    ['label' => 'My Complaints', 'route_name' => 'dashboard', 'icon' => 'complaints'],
                    ['label' => 'Lodge a Complaint', 'route_name' => 'complaints.create', 'icon' => 'lodge'],
                    ['label' => 'Track by Ticket', 'icon' => 'track'],
                ],
                'Account' => [
                    ['label' => 'Dashboard', 'route_name' => 'dashboard', 'icon' => 'dashboard'],
                    ['label' => 'Profile', 'icon' => 'profile'],
                    ['label' => 'Change Password', 'route_name' => 'password.change', 'icon' => 'shield'],
                    ['label' => 'Help & Contact', 'icon' => 'help'],
                ],
            ],
            PortalMenuItem::AUDIENCE_REGIONAL_MANAGER => [
                'Complaints' => [
                    ['label' => 'Relationship Queue', 'route_name' => 'dashboard', 'icon' => 'complaints'],
                    ['label' => 'Forwarded by Me', 'icon' => 'track'],
                ],
                'Account' => [
                    ['label' => 'Dashboard', 'route_name' => 'dashboard', 'icon' => 'dashboard'],
                    ['label' => 'Profile', 'icon' => 'profile'],
                    ['label' => 'Site Policies', 'icon' => 'help'],
                ],
            ],
            PortalMenuItem::AUDIENCE_COMPETENT_AUTHORITY => [
                'Complaints' => [
                    ['label' => 'Assigned Queue', 'route_name' => 'dashboard', 'icon' => 'complaints'],
                    ['label' => 'Resolved by Me', 'icon' => 'track'],
                ],
                'Account' => [
                    ['label' => 'Dashboard', 'route_name' => 'dashboard', 'icon' => 'dashboard'],
                    ['label' => 'Profile', 'icon' => 'profile'],
                    ['label' => 'Site Policies', 'icon' => 'help'],
                ],
            ],
            PortalMenuItem::AUDIENCE_SITE_ADMIN => [
                'Administration' => [
                    ['label' => 'Dashboard', 'route_name' => 'dashboard', 'icon' => 'dashboard'],
                    ['label' => 'Manage Menus', 'route_name' => 'admin.menu.index', 'icon' => 'sliders'],
                    ['label' => 'Manage Complaint Categories', 'route_name' => 'admin.complaint-categories.index', 'icon' => 'complaints'],
                    ['label' => 'Create Role', 'route_name' => 'admin.roles.index', 'icon' => 'users'],
                ],
                'Account' => [
                    ['label' => 'Profile', 'icon' => 'profile'],
                    ['label' => 'Help & Contact', 'icon' => 'help'],
                ],
            ],
        ];

        foreach ($menus as $audience => $menuSections) {
            foreach ($menuSections as $section => $items) {
                foreach ($items as $item) {
                    PortalMenuItem::create([
                        'audience' => $audience,
                        'section' => $section,
                        'label' => $item['label'],
                        'route_name' => $item['route_name'] ?? null,
                        'icon' => $item['icon'],
                    ]);
                }
            }
        }
    }
}
