<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var User $user */
        $user = $this->resource;

        return static::summary($user);
    }

    /**
     * Summarize a user for embedding into other resources.
     *
     * @return array<string, mixed>
     */
    public static function summary(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role?->value,
            'region' => $user->region,
            'mobile' => $user->mobile,
            'address' => $user->address,
            'designation' => $user->designation,
            'discom_id' => $user->discom_id,
            'circle_id' => $user->circle_id,
            'division_id' => $user->division_id,
            'subdivision_id' => $user->subdivision_id,
            'section_id' => $user->section_id,
        ];
    }
}
