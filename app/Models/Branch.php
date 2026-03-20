<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Branch extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'branch_tag_id',
        'pastor_name',
        'pastor_phone',
        'pastor_email',
        'address',
        'city',
        'state',
        'district_area',
        'email',
        'phone',
        'status',
        'created_by_church_id',
        'created_by_user_id',
        'created_by_actor_type',
        'current_parent_church_id',
        'current_parent_branch_id',
        'last_assigned_by_church_id',
        'last_assigned_by_user_id',
        'last_assigned_actor_type',
    ];

    public function tag(): BelongsTo
    {
        return $this->belongsTo(BranchTag::class, 'branch_tag_id');
    }

    public function creatorChurch(): BelongsTo
    {
        return $this->belongsTo(Church::class, 'created_by_church_id');
    }

    public function creatorUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function currentParentChurch(): BelongsTo
    {
        return $this->belongsTo(Church::class, 'current_parent_church_id');
    }

    public function currentParentBranch(): BelongsTo
    {
        return $this->belongsTo(self::class, 'current_parent_branch_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'current_parent_branch_id');
    }

    public function lastAssignedByChurch(): BelongsTo
    {
        return $this->belongsTo(Church::class, 'last_assigned_by_church_id');
    }

    public function lastAssignedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_assigned_by_user_id');
    }

    public function assignmentHistories(): HasMany
    {
        return $this->hasMany(BranchAssignmentHistory::class)->latest();
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function localAdmin(): HasMany
    {
        return $this->users()->where('role', 'branch_admin')->latest('id');
    }

    public function homecells(): HasMany
    {
        return $this->hasMany(Homecell::class)->latest('name');
    }

    public function homecellAttendanceRecords(): HasMany
    {
        return $this->hasMany(HomecellAttendanceRecord::class)->latest('meeting_date');
    }
}
