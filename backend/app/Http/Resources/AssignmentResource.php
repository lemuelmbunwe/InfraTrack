<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AssignmentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'instructions' => $this->instructions,
            'initial_contractor' => $this->whenLoaded('initialContractor', fn (): array => [
                'id' => $this->initialContractor->id,
                'name' => $this->initialContractor->name,
            ]),
            'contractors' => $this->whenLoaded('contractors', fn () => $this->contractors->map(fn ($contractor): array => [
                'id' => $contractor->id,
                'name' => $contractor->name,
            ])->values()),
            'issues' => $this->whenLoaded('issues', fn () => $this->issues->map(function ($issue): array {
                $contractor = $issue->activeAssignment?->contractor;

                return [
                    'id' => $issue->id,
                    'status' => $issue->status,
                    'assigned_contractor' => $contractor === null ? null : [
                        'id' => $contractor->id,
                        'name' => $contractor->name,
                    ],
                ];
            })->values()),
        ];
    }
}
