<?php

namespace App\Models;

use Database\Factories\IssueAssignmentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IssueAssignment extends Model
{
    /** @use HasFactory<IssueAssignmentFactory> */
    use HasFactory;

    protected $fillable = [
        'issue_id',
        'contractor_id',
        'assigned_by',
        'assigned_at',
        'unassigned_at',
    ];

    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
            'unassigned_at' => 'datetime',
        ];
    }

    public function issue(): BelongsTo
    {
        return $this->belongsTo(Issue::class);
    }

    public function contractor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'contractor_id');
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('unassigned_at');
    }
}
