<?php

namespace App\Http\Resources;

use App\Models\Consumer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ComplaintActionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'action' => $this->action,
            'from_status' => $this->from_status,
            'to_status' => $this->to_status,
            'from_level' => $this->from_level,
            'to_level' => $this->to_level,
            'comment' => $this->comment,
            'actor_type' => $this->actor_type,
            'actor' => $this->whenLoaded('actor', fn (): array => $this->actor instanceof Consumer
                ? ConsumerResource::summary($this->actor)
                : UserResource::summary($this->actor)),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
