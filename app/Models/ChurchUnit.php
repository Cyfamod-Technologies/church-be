<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ChurchUnit extends Model
{
    use HasFactory;

    protected $fillable = [
        'church_id',
        'name',
        'code',
        'description',
        'status',
    ];

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(
            GuestResponseEntry::class,
            'guest_response_entry_church_unit',
            'church_unit_id',
            'guest_response_entry_id'
        )->withTimestamps();
    }
}
