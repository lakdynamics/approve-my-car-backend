<?php

namespace App\Models;

use Database\Factories\ChecklistAnswerSetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A reusable list of answers for "pick one" checklist items,
 * e.g. "Condition": Good / Bad / None.
 */
#[Fillable(['name', 'is_active'])]
class ChecklistAnswerSet extends Model
{
    /** @use HasFactory<ChecklistAnswerSetFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<ChecklistAnswerOption, $this>
     */
    public function options(): HasMany
    {
        return $this->hasMany(ChecklistAnswerOption::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Checklist items that use this list.
     *
     * @return HasMany<ChecklistItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(ChecklistItem::class);
    }

    /**
     * @param  Builder<ChecklistAnswerSet>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @param  Builder<ChecklistAnswerSet>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('name');
    }
}
