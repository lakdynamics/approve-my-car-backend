<?php

namespace App\Models;

use Database\Factories\InspectionSectionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A checklist section as it was when the inspection started, plus the
 * inspector's comment for it.
 */
class InspectionSection extends Model
{
    /** @use HasFactory<InspectionSectionFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Inspection, $this>
     */
    public function inspection(): BelongsTo
    {
        return $this->belongsTo(Inspection::class);
    }

    /**
     * @return HasMany<InspectionAnswer, $this>
     */
    public function answers(): HasMany
    {
        return $this->hasMany(InspectionAnswer::class)->orderBy('sort_order')->orderBy('id');
    }
}
