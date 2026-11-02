<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Circle extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'discom_id',
        'code',
        'name',
        'short_name',
        'is_active',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get the discom that owns the circle.
     */
    public function discom(): BelongsTo
    {
        return $this->belongsTo(Discom::class);
    }

    /**
     * Get the divisions for the circle.
     */
    public function divisions(): HasMany
    {
        return $this->hasMany(Division::class);
    }
}
