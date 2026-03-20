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
        'status',
    ];

    protected function casts(): array
    {
        return [
            'finance_enabled' => 'boolean',
            'special_services_enabled' => 'boolean',
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
}
