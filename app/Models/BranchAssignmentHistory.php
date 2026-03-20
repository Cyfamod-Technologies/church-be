<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BranchAssignmentHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'action_type',
        'from_parent_church_id',
        'from_parent_branch_id',
        'to_parent_church_id',
        'to_parent_branch_id',
        'changed_by_church_id',
        'changed_by_user_id',
        'changed_by_actor_type',
        'note',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function fromParentChurch(): BelongsTo
    {
        return $this->belongsTo(Church::class, 'from_parent_church_id');
    }

    public function fromParentBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'from_parent_branch_id');
    }

    public function toParentChurch(): BelongsTo
    {
        return $this->belongsTo(Church::class, 'to_parent_church_id');
    }

    public function toParentBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'to_parent_branch_id');
    }

    public function changedByChurch(): BelongsTo
    {
        return $this->belongsTo(Church::class, 'changed_by_church_id');
    }

    public function changedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by_user_id');
    }
}
