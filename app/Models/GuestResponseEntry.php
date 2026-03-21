<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class GuestResponseEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'church_id',
        'branch_id',
        'recorded_by_user_id',
        'entry_type',
        'full_name',
        'phone',
        'email',
        'gender',
        'service_date',
        'invited_by',
        'address',
        'notes',
        'foundation_class_completed',
        'baptism_completed',
        'holy_ghost_baptism_completed',
        'wofbi_completed',
        'wofbi_level',
        'wofbi_levels',
    ];

    protected function casts(): array
    {
        return [
            'service_date' => 'date',
            'foundation_class_completed' => 'boolean',
            'baptism_completed' => 'boolean',
            'holy_ghost_baptism_completed' => 'boolean',
            'wofbi_completed' => 'boolean',
            'wofbi_levels' => 'array',
        ];
    }

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }

    public function churchUnits(): BelongsToMany
    {
        return $this->belongsToMany(
            ChurchUnit::class,
            'guest_response_entry_church_unit',
            'guest_response_entry_id',
            'church_unit_id'
        )->withTimestamps();
    }
}
