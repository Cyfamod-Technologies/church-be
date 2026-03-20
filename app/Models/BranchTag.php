<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class BranchTag extends Model
{
    use HasFactory;

    protected $fillable = [
        'church_id',
        'name',
        'slug',
    ];

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    public static function ensureDefaults(): void
    {
        foreach (['District', 'Zone', 'Sub-District', 'Area', 'Branch'] as $name) {
            static::firstOrCreate(
                [
                    'church_id' => null,
                    'slug' => Str::slug($name),
                ],
                [
                    'name' => $name,
                ]
            );
        }
    }
}
