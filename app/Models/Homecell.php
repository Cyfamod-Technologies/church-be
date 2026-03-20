<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Homecell extends Model
{
    use HasFactory;

    protected $fillable = [
        'church_id',
        'branch_id',
        'name',
        'code',
        'meeting_day',
        'meeting_time',
        'host_name',
        'host_phone',
        'city_area',
        'address',
        'notes',
        'status',
    ];

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function leaders(): HasMany
    {
        return $this->hasMany(HomecellLeader::class)->orderBy('sort_order')->orderByDesc('is_primary');
    }
}
