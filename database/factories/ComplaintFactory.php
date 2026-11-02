<?php

namespace Database\Factories;

use App\Enums\ComplaintCategory;
use App\Enums\ComplaintLevel;
use App\Enums\ComplaintStatus;
use App\Models\Complaint;
use App\Models\Consumer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Complaint>
 */
class ComplaintFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'consumer_number' => fake()->unique()->numerify('##########'),
            'category' => fake()->randomElement(ComplaintCategory::cases()),
            'subject' => fake()->sentence(),
            'description' => fake()->paragraph(),
            'region' => 'North',
            'status' => ComplaintStatus::Submitted,
            'current_level' => ComplaintLevel::RegionalManager,
        ];
    }

    /**
     * Attach the complaint to the given consumer, inheriting their region.
     */
    public function forConsumer(Consumer $consumer): static
    {
        return $this->for($consumer, 'consumer')->state(fn (): array => [
            'region' => $consumer->region ?? 'North',
        ]);
    }

    /**
     * Mark the complaint as forwarded to the given competent authority.
     */
    public function forwardedBy(User $regionalManager, User $competentAuthority): static
    {
        return $this->state(fn (): array => [
            'status' => ComplaintStatus::Forwarded,
            'current_level' => ComplaintLevel::CompetentAuthority,
            'regional_manager_id' => $regionalManager->id,
            'competent_authority_id' => $competentAuthority->id,
        ]);
    }
}
