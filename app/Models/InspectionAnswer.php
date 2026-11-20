<?php

namespace App\Models;

use App\Enums\AnswerOutcome;
use App\Enums\AnswerType;
use Database\Factories\InspectionAnswerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One checklist item as it was when the inspection started (label, type,
 * options, limits) and the inspector's answer to it.
 */
class InspectionAnswer extends Model
{
    /** @use HasFactory<InspectionAnswerFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'answer_type' => AnswerType::class,
            'outcome' => AnswerOutcome::class,
            'options' => 'array',
            'min_value' => 'float',
            'max_value' => 'float',
            'problem_below' => 'float',
            'problem_above' => 'float',
            'number_value' => 'float',
            'sort_order' => 'integer',
            'option_id' => 'integer',
            'answered_at' => 'datetime',
        ];
    }

    public function isAnswered(): bool
    {
        return $this->answered_at !== null;
    }

    /**
     * Text items (e.g. "Extras") may be left empty.
     */
    public function isRequired(): bool
    {
        return $this->answer_type !== AnswerType::Text;
    }

    /**
     * @return array{id: int, label: string, outcome: string}|null
     */
    public function option(int $id): ?array
    {
        return collect($this->options ?? [])->firstWhere('id', $id);
    }

    /**
     * @return BelongsTo<Inspection, $this>
     */
    public function inspection(): BelongsTo
    {
        return $this->belongsTo(Inspection::class);
    }

    /**
     * @return BelongsTo<InspectionSection, $this>
     */
    public function section(): BelongsTo
    {
        return $this->belongsTo(InspectionSection::class, 'inspection_section_id');
    }
}
