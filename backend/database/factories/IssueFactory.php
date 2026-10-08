<?php

namespace Database\Factories;

use App\Models\Issue;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Issue>
 */
class IssueFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reported_by' => User::factory(),
            'photo_path' => 'issues/test-photo.jpg',
            'latitude' => 3.848,
            'longitude' => 11.502,
            'address' => null,
            'address_text' => null,
            'description' => null,
            'severity' => 'medium',
            'status' => 'reported',
            'reported_at' => now(),
        ];
    }

    public function reportedBy(User $user): static
    {
        return $this->for($user, 'reporter');
    }
}
