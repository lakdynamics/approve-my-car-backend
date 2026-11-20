<?php

namespace App\Models;

use App\Enums\AnswerType;
use Database\Factories\ChecklistItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One check in a checklist section, e.g. "Oil level and condition".
 * sort_order and the section are set by ChecklistService.
 */
#[Fillable([
    'label', 'description', 'is_active',
    'answer_type', 'checklist_answer_set_id', 'unit',
    'min_value', 'max_value', 'problem_below', 'problem_above',
])]
class ChecklistItem extends Model
{
    /** @use HasFactory<ChecklistItemFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'answer_type' => AnswerType::class,
            'min_value' => 'float',
            'max_value' => 'float',
            'problem_below' => 'float',
            'problem_above' => 'float',
        ];
    }

    /**
     * @return BelongsTo<ChecklistSection, $this>
     */
    public function section(): BelongsTo
    {
        return $this->belongsTo(ChecklistSection::class, 'checklist_section_id');
    }

    /**
     * The answer list of a "pick one" item.
     *
     * @return BelongsTo<ChecklistAnswerSet, $this>
     */
    public function answerSet(): BelongsTo
    {
        return $this->belongsTo(ChecklistAnswerSet::class, 'checklist_answer_set_id');
    }

    /**
     * @param  Builder<ChecklistItem>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @param  Builder<ChecklistItem>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('id');
    }
}
