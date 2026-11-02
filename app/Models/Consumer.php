<?php

namespace App\Models;

use Database\Factories\ConsumerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * An external user: an HT electricity consumer. Officials live in the
 * `users` table; consumers authenticate over mobile OTP (then set their
 * first password) through their dedicated auth guard.
 */
#[Fillable(['name', 'email', 'consumer_number', 'mobile', 'address', 'load', 'meter_number', 'tariff', 'region', 'password', 'discom_id', 'circle_id', 'division_id', 'subdivision_id', 'section_id'])]
#[Hidden(['password'])]
class Consumer extends Authenticatable
{
    /** @use HasFactory<ConsumerFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }

    /**
     * Complaints raised by this consumer.
     */
    public function complaints(): HasMany
    {
        return $this->hasMany(Complaint::class);
    }

    public function discom(): BelongsTo
    {
        return $this->belongsTo(Discom::class);
    }

    public function circle(): BelongsTo
    {
        return $this->belongsTo(Circle::class);
    }

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    public function subdivision(): BelongsTo
    {
        return $this->belongsTo(Subdivision::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }
}
