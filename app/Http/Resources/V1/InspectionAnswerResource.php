<?php

namespace App\Http\Resources\V1;

use App\Enums\AnswerType;
use App\Models\InspectionAnswer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One item of the inspection (as copied when it started) and its answer.
 * "answer" is null until the inspector answers it.
 *
 * @mixin InspectionAnswer
 */
class InspectionAnswerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $numeric = in_array($this->answer_type, [AnswerType::Number, AnswerType::Percentage], true);

        return [
            'id' => $this->id,
            'label' => $this->label,
            'description' => $this->description,
            'answer_type' => $this->answer_type,
            'required' => $this->isRequired(),
            'options' => $this->when($this->answer_type === AnswerType::Choice, fn () => $this->options ?? []),
            'unit' => $this->when($this->unit !== null, $this->unit),
            'min_value' => $this->when($this->min_value !== null, $this->min_value),
            'max_value' => $this->when($this->max_value !== null, $this->max_value),
            'problem_below' => $this->when($this->problem_below !== null, $this->problem_below),
            'problem_above' => $this->when($this->problem_above !== null, $this->problem_above),
            'answer' => $this->isAnswered() ? [
                'option_id' => $this->option_id,
                'label' => $this->option_label,
                'value' => $numeric ? $this->number_value : null,
                'text' => $this->text_value,
                'note' => $this->note,
                'outcome' => $this->outcome,
                'answered_at' => $this->answered_at,
            ] : null,
        ];
    }
}
