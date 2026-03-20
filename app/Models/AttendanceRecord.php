<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'church_id',
        'service_schedule_id',
        'recorded_by_user_id',
        'service_date',
        'service_type',
        'service_label',
        'sunday_service_number',
        'special_service_name',
        'male_count',
        'female_count',
        'children_count',
        'total_count',
        'first_timers_count',
        'new_converts_count',
        'rededications_count',
        'main_offering',
        'tithe',
        'special_offering',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'service_date' => 'date',
            'main_offering' => 'decimal:2',
            'tithe' => 'decimal:2',
            'special_offering' => 'decimal:2',
        ];
    }

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }

    public function serviceSchedule(): BelongsTo
    {
        return $this->belongsTo(ServiceSchedule::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }
}
