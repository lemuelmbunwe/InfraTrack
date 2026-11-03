<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Assignment extends Model
{
    protected $fillable = [
        'title',
        'instructions',
        'assigned_by',
        'initial_contractor_id',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function initialContractor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initial_contractor_id');
    }

    public function issueAllocations(): HasMany
    {
        return $this->hasMany(IssueAssignment::class);
    }

    public function issues(): BelongsToMany
    {
        return $this->belongsToMany(Issue::class, 'issue_assignments')
            ->wherePivotNull('unassigned_at');
    }

    public function contractors(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'issue_assignments',
            'assignment_id',
            'contractor_id',
        )
            ->wherePivotNull('unassigned_at')
            ->distinct();
    }
}
