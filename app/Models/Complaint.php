<?php

namespace App\Models;

use App\Enums\ComplaintCategory;
use App\Enums\ComplaintLevel;
use App\Enums\ComplaintStatus;
use Database\Factories\ComplaintFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable(['consumer_number', 'category', 'subject', 'description', 'region', 'attachments', 'authority_attachments'])]
class Complaint extends Model
{
    /** @use HasFactory<ComplaintFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => ComplaintCategory::class,
            'status' => ComplaintStatus::class,
            'current_level' => ComplaintLevel::class,
            'resolved_at' => 'datetime',
            'attachments' => 'array',
            'authority_attachments' => 'array',
        ];
    }

    /**
     * Generate a unique ELEC-prefixed ticket number when a complaint is created.
     */
    protected static function booted(): void
    {
        static::creating(function (Complaint $complaint): void {
            $complaint->ticket_number ??= static::generateTicketNumber();
        });
    }

    /**
     * Build a unique ticket number, relying on the database's unique index as the final guard.
     */
    public static function generateTicketNumber(): string
    {
        do {
            $ticketNumber = 'ELEC-'.Str::upper(Str::random(8));
        } while (static::query()->where('ticket_number', $ticketNumber)->exists());

        return $ticketNumber;
    }

    /**
     * Restrict the query to complaints raised in the given region.
     */
    #[Scope]
    protected function forRegion(Builder $query, string $region): Builder
    {
        return $query->where('region', $region);
    }

    /**
     * The consumer who raised the complaint.
     */
    public function consumer(): BelongsTo
    {
        return $this->belongsTo(Consumer::class, 'consumer_id');
    }

    /**
     * The regional manager assigned to review the complaint.
     */
    public function regionalManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'regional_manager_id');
    }

    /**
     * The competent authority the complaint was escalated to.
     */
    public function competentAuthority(): BelongsTo
    {
        return $this->belongsTo(User::class, 'competent_authority_id');
    }

    /**
     * The audit trail entries recorded against the complaint.
     */
    public function actions(): HasMany
    {
        return $this->hasMany(ComplaintAction::class);
    }
}
