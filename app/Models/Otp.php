<?php

namespace App\Models;

use Database\Factories\OtpFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Hash;

#[Fillable(['consumer_id', 'code_hash', 'expires_at', 'consumed_at', 'attempts'])]
class Otp extends Model
{
    /** @use HasFactory<OtpFactory> */
    use HasFactory;

    /**
     * The number of digits in a login code.
     */
    public const CODE_LENGTH = 6;

    /**
     * Minutes a code stays valid after issuance.
     */
    public const TTL_MINUTES = 5;

    /**
     * Wrong entries allowed before the code must be re-issued.
     */
    public const MAX_ATTEMPTS = 3;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
            'attempts' => 'integer',
        ];
    }

    /**
     * Issue a fresh code for the consumer, burning any previously issued unconsumed codes.
     *
     * @return array{0: Otp, 1: string} The persisted OTP row and its plain-text code.
     */
    public static function issue(Consumer $consumer): array
    {
        static::query()
            ->where('consumer_id', $consumer->id)
            ->whereNull('consumed_at')
            ->update(['consumed_at' => now()]);

        $code = str_pad((string) random_int(0, 999999), self::CODE_LENGTH, '0', STR_PAD_LEFT);

        $otp = static::query()->create([
            'consumer_id' => $consumer->id,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(self::TTL_MINUTES),
            'attempts' => 0,
        ]);

        return [$otp, $code];
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isConsumed(): bool
    {
        return $this->consumed_at !== null;
    }

    public function hasExhaustedAttempts(): bool
    {
        return $this->attempts >= self::MAX_ATTEMPTS;
    }

    /**
     * Record a failed-entry attempt with an atomic increment.
     */
    public function registerAttempt(): void
    {
        static::query()->whereKey($this->getKey())->increment('attempts');
    }

    /**
     * Mark the code as consumed after a successful match.
     */
    public function consume(): void
    {
        $this->forceFill(['consumed_at' => now()])->save();
    }

    public function matches(string $code): bool
    {
        return Hash::check($code, $this->code_hash);
    }

    /**
     * The consumer account the code was issued for.
     */
    public function consumer(): BelongsTo
    {
        return $this->belongsTo(Consumer::class);
    }
}
