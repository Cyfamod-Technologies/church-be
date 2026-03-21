<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HomecellLeader extends Model
{
    use HasFactory;

    protected $fillable = [
        'homecell_id',
        'user_id',
        'name',
        'role',
        'phone',
        'email',
        'is_primary',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
        ];
    }

    public function homecell(): BelongsTo
    {
        return $this->belongsTo(Homecell::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
