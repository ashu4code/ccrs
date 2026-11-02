<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ComplaintResource extends JsonResource
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
            'ticket_number' => $this->ticket_number,
            'consumer_number' => $this->consumer_number,
            'category' => $this->category?->value,
            'subject' => $this->subject,
            'description' => $this->description,
            'region' => $this->region,
            'status' => $this->status?->value,
            'current_level' => $this->current_level?->value,
            'resolved_at' => $this->resolved_at?->toISOString(),
            'consumer' => $this->whenLoaded('consumer', fn (): array => ConsumerResource::summary($this->consumer)),
            'regional_manager' => $this->whenLoaded('regionalManager', fn (): array => UserResource::summary($this->regionalManager)),
            'competent_authority' => $this->whenLoaded('competentAuthority', fn (): array => UserResource::summary($this->competentAuthority)),
            'actions' => ComplaintActionResource::collection($this->whenLoaded('actions')),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
