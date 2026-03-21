<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
    ];

    protected function casts(): array
    {
        return [
            'service_date' => 'date',
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
}
