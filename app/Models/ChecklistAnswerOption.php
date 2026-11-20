<?php

namespace App\Models;

use App\Enums\AnswerOutcome;
use Database\Factories\ChecklistAnswerOptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One answer in an answer list, e.g. "Bad" (a problem).
 * sort_order and the list are set by ChecklistService.
 */
#[Fillable(['label', 'outcome', 'is_active'])]
class ChecklistAnswerOption extends Model
{
    /** @use HasFactory<ChecklistAnswerOptionFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'outcome' => AnswerOutcome::class,
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<ChecklistAnswerSet, $this>
     */
    public function set(): BelongsTo
    {
        return $this->belongsTo(ChecklistAnswerSet::class, 'checklist_answer_set_id');
    }

    /**
     * @param  Builder<ChecklistAnswerOption>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @param  Builder<ChecklistAnswerOption>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('id');
    }
}
