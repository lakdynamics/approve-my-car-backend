<?php

namespace App\Http\Requests\V1\Admin\Checklist\Concerns;

use App\Enums\AnswerType;
use App\Models\ChecklistItem;
use Closure;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/**
 * The answer settings of a checklist item. Which fields are allowed
 * depends on the answer type: the one sent, or the item's current type
 * when editing without changing it.
 *
 * - choice: checklist_answer_set_id
 * - number: unit, min_value, max_value, problem_below, problem_above
 * - percentage: problem_below, problem_above (0 to 100)
 * - text: nothing
 */
trait ValidatesAnswerType
{
    /**
     * @return array<string, array<mixed>>
     */
    protected function answerRules(?ChecklistItem $item = null): array
    {
        $type = $this->answerType($item);

        if ($type === null) {
            // answer_type itself fails; the other fields don't matter yet.
            return ['answer_type' => [$item ? 'sometimes' : 'required', Rule::enum(AnswerType::class)]];
        }

        // When the type stays the same, unsent settings keep their saved values.
        $current = fn (string $field) => $this->has($field)
            ? $this->input($field)
            : ($item?->answer_type === $type ? $item->{$field} : null);

        $isNumber = $type === AnswerType::Number;
        $hasLimits = $type->hasProblemLimits();
        $limitRange = $type === AnswerType::Percentage ? ['between:0,100'] : [];
        $needsNewSet = $item === null || $item->answer_type !== AnswerType::Choice;

        return [
            'answer_type' => [$item ? 'sometimes' : 'required', Rule::enum(AnswerType::class)],
            'checklist_answer_set_id' => $type === AnswerType::Choice
                ? [$needsNewSet ? 'required' : 'sometimes', 'required', 'integer', $this->usableAnswerSet($item)]
                : ['prohibited'],
            'unit' => $isNumber ? ['nullable', 'string', 'max:20'] : ['prohibited'],
            'min_value' => $isNumber
                ? ['nullable', 'numeric', $this->inOrder(min: null, max: $current('max_value'))]
                : ['prohibited'],
            'max_value' => $isNumber
                ? ['nullable', 'numeric', $this->inOrder(min: $current('min_value'), max: null)]
                : ['prohibited'],
            'problem_below' => $hasLimits ? ['nullable', 'numeric', ...$limitRange] : ['prohibited'],
            'problem_above' => $hasLimits ? ['nullable', 'numeric', ...$limitRange] : ['prohibited'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function answerMessages(?ChecklistItem $item = null): array
    {
        $unused = 'Not used for '.($this->answerType($item)?->value ?? 'this type of').' items.';

        return [
            'checklist_answer_set_id.required' => 'Select an answer list.',
            'checklist_answer_set_id.exists' => 'Select an active answer list.',
            'checklist_answer_set_id.prohibited' => $unused,
            'unit.prohibited' => $unused,
            'min_value.prohibited' => $unused,
            'max_value.prohibited' => $unused,
            'problem_below.prohibited' => $unused,
            'problem_above.prohibited' => $unused,
        ];
    }

    private function answerType(?ChecklistItem $item): ?AnswerType
    {
        if ($this->has('answer_type')) {
            return is_string($this->input('answer_type')) ? AnswerType::tryFrom($this->input('answer_type')) : null;
        }

        return $item?->answer_type;
    }

    /**
     * An active list. When editing, the item's current list is also fine
     * even if an admin has hidden it since.
     */
    private function usableAnswerSet(?ChecklistItem $item): Exists
    {
        $currentSetId = $item?->checklist_answer_set_id;

        return Rule::exists('checklist_answer_sets', 'id')->where(
            fn ($query) => $query->where(
                fn ($query) => $query->where('is_active', true)
                    ->when($currentSetId, fn ($query) => $query->orWhere('id', $currentSetId))
            )
        );
    }

    /**
     * Checks the value against the other end of the range (the one sent,
     * or the saved one), so an edit can't leave min above max.
     */
    private function inOrder(mixed $min, mixed $max): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($min, $max): void {
            if (! is_numeric($value)) {
                return;
            }

            if ((is_numeric($min) && $value < $min) || (is_numeric($max) && $value > $max)) {
                $fail('The maximum must be at least the minimum.');
            }
        };
    }
}
