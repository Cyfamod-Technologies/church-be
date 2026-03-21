<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Church extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'address',
        'city',
        'state',
        'district_area',
        'email',
        'phone',
        'pastor_name',
        'pastor_phone',
        'pastor_email',
        'finance_enabled',
        'special_services_enabled',
        'homecell_schedule_locked',
        'homecell_default_day',
        'homecell_default_time',
        'homecell_monthly_dates',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'finance_enabled' => 'boolean',
            'special_services_enabled' => 'boolean',
            'homecell_schedule_locked' => 'boolean',
            'homecell_monthly_dates' => 'array',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function serviceSchedules(): HasMany
    {
        return $this->hasMany(ServiceSchedule::class)->orderBy('sort_order');
    }

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class)->latest('service_date');
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
