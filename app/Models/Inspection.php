<?php

namespace App\Models;

use Database\Factories\InspectionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The inspector's filled-in checklist for one booking. Nothing is mass
 * assignable: InspectionService sets every field.
 */
class Inspection extends Model
{
    /** @use HasFactory<InspectionFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function isCompleted(): bool
    {
        return $this->completed_at !== null;
    }

    /**
     * @return BelongsTo<Booking, $this>
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function inspector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inspector_id')->withTrashed();
    }

    /**
     * @return HasMany<InspectionSection, $this>
     */
    public function sections(): HasMany
    {
        return $this->hasMany(InspectionSection::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return HasMany<InspectionAnswer, $this>
     */
    public function answers(): HasMany
    {
        return $this->hasMany(InspectionAnswer::class);
    }
}
