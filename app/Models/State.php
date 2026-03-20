<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class State extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'display_order',
    ];

    public function localGovernmentAreas(): HasMany
    {
        return $this->hasMany(LocalGovernmentArea::class)->orderBy('name');
    }
}
