<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class IssueResource extends JsonResource
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
            'reporter' => $this->whenLoaded('reporter', fn (): array => [
                'id' => $this->reporter->id,
                'name' => $this->reporter->name,
            ]),
            'photo_url' => route('api.v1.issues.photo', ['issue' => $this->id]),
            'latitude' => (float) $this->latitude,
            'longitude' => (float) $this->longitude,
            'address' => $this->address,
            'address_text' => $this->address_text,
            'description' => $this->description,
            'severity' => $this->severity,
            'status' => $this->status,
            'reported_at' => $this->reported_at?->toIso8601String(),
            'status_history' => $this->whenLoaded('statusHistory', fn () => $this->statusHistory->map(fn ($entry): array => [
                'old_status' => $entry->old_status,
                'new_status' => $entry->new_status,
                'message' => $entry->message,
                'changed_at' => $entry->changed_at?->toIso8601String(),
            ])),
        ];
    }
}
