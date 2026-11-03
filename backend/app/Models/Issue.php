<?php

namespace App\Models;

use Database\Factories\IssueFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Issue extends Model
{
    /** @use HasFactory<IssueFactory> */
    use HasFactory;

    protected $fillable = [
        'reported_by',
        'photo_path',
        'latitude',
        'longitude',
        'address',
        'address_text',
        'description',
        'severity',
        'status',
        'reported_at',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'reported_at' => 'datetime',
        ];
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(IssueStatusHistory::class)->orderBy('changed_at');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(IssueAssignment::class)->orderBy('assigned_at');
    }

    public function workAssignments(): BelongsToMany
    {
        return $this->belongsToMany(Assignment::class, 'issue_assignments')
            ->wherePivotNull('unassigned_at');
    }

    public function activeAssignment(): HasOne
    {
        return $this->hasOne(IssueAssignment::class)->ofMany(
            ['assigned_at' => 'max', 'id' => 'max'],
            fn ($query) => $query->whereNull('unassigned_at'),
        );
    }
}
