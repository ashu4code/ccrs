<?php

namespace App\Models;

use Database\Factories\ComplaintActionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable(['complaint_id', 'actor_type', 'actor_id', 'action', 'from_status', 'to_status', 'from_level', 'to_level', 'competent_authority_id', 'comment'])]
class ComplaintAction extends Model
{
    /** @use HasFactory<ComplaintActionFactory> */
    use HasFactory;

    /**
     * The complaint the action was recorded against.
     */
    public function complaint(): BelongsTo
    {
        return $this->belongsTo(Complaint::class);
    }

    /**
     * Whoever performed the action — a Consumer or an official User,
     * resolved through the morph map (consumer | official).
     */
    public function actor(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * The competent authority recorded by the action, when applicable.
     */
    public function competentAuthority(): BelongsTo
    {
        return $this->belongsTo(User::class, 'competent_authority_id');
    }
}
