<?php

namespace Database\Factories;

use App\Models\Issue;
use App\Models\IssueAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IssueAssignment>
 */
class IssueAssignmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'issue_id' => Issue::factory(),
            'contractor_id' => User::factory(),
            'assigned_by' => User::factory(),
            'assigned_at' => now(),
            'unassigned_at' => null,
        ];
    }
}
