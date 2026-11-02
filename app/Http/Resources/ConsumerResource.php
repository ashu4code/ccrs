<?php

namespace App\Http\Resources;

use App\Models\Consumer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConsumerResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Consumer $consumer */
        $consumer = $this->resource;

        return static::summary($consumer);
    }

    /**
     * Summarize a consumer for embedding into other resources.
     *
     * @return array<string, mixed>
     */
    public static function summary(Consumer $consumer): array
    {
        return [
            'id' => $consumer->id,
            'name' => $consumer->name,
            'email' => $consumer->email,
            'consumer_number' => $consumer->consumer_number,
            'mobile' => $consumer->mobile,
            'address' => $consumer->address,
            'load' => $consumer->load,
            'meter_number' => $consumer->meter_number,
            'tariff' => $consumer->tariff,
            'region' => $consumer->region,
            'discom_id' => $consumer->discom_id,
            'circle_id' => $consumer->circle_id,
            'division_id' => $consumer->division_id,
            'subdivision_id' => $consumer->subdivision_id,
            'section_id' => $consumer->section_id,
        ];
    }
}
