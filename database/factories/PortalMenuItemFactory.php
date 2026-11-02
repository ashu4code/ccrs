<?php

namespace Database\Factories;

use App\Models\PortalMenuItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PortalMenuItem>
 */
class PortalMenuItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'audience' => fake()->randomElement(PortalMenuItem::audiences()),
            'section' => fake()->randomElement(['Complaints', 'Account', 'Administration']),
            'label' => fake()->words(2, true),
            'route_name' => null,
            'url' => null,
            'icon' => fake()->randomElement(['dashboard', 'complaints', 'track', 'lodge', 'profile', 'help', 'sliders', 'shield']),
            'sort_order' => null,
            'is_enabled' => true,
        ];
    }

    /**
     * Pin the item to a given audience and sidebar section.
     */
    public function forAudience(string $audience, string $section = 'Complaints'): static
    {
        return $this->state(fn (): array => [
            'audience' => $audience,
            'section' => $section,
            'icon' => 'complaints',
        ]);
    }

    /**
     * Point the item at a named route so it renders as a live link.
     */
    public function target(string $routeName): static
    {
        return $this->state(fn (): array => [
            'route_name' => $routeName,
            'url' => null,
        ]);
    }

    /**
     * Mark the item as the enabled-false state (hidden from the sidebar).
     */
    public function disabled(): static
    {
        return $this->state(fn (): array => [
            'is_enabled' => false,
        ]);
    }

    /**
     * A human-friendly deterministic label pattern for tests.
     */
    public function labelled(string $label): static
    {
        return $this->state(fn (): array => [
            'label' => $label,
        ]);
    }
}
