<?php

namespace Database\Factories;

use App\Enums\ComplaintLevel;
use App\Enums\ComplaintStatus;
use App\Models\ComplaintAction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ComplaintAction>
 */
class ComplaintActionFactory extends Factory
{
    /**
     * Define the model's default state. The actor (consumer or official) is
     * attached by callers via `actor()->associate()`.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'action' => 'submitted',
            'from_status' => null,
            'to_status' => ComplaintStatus::Submitted->value,
            'from_level' => null,
            'to_level' => ComplaintLevel::RegionalManager->value,
        ];
    }
}
