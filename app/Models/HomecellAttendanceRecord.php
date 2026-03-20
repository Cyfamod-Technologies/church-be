<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HomecellAttendanceRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'church_id',
        'branch_id',
        'homecell_id',
        'recorded_by_user_id',
        'meeting_date',
        'male_count',
        'female_count',
        'children_count',
        'total_count',
        'first_timers_count',
        'new_converts_count',
        'offering_amount',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'meeting_date' => 'date',
            'offering_amount' => 'decimal:2',
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

    public function homecell(): BelongsTo
    {
        return $this->belongsTo(Homecell::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }
}
