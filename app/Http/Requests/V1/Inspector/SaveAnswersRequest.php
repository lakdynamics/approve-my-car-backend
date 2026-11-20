<?php

namespace App\Http\Requests\V1\Inspector;

use App\Enums\AnswerType;
use App\Models\Booking;
use App\Models\Inspection;
use App\Models\InspectionAnswer;
use App\Services\Inspections\InspectionService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator;

/**
 * A batch of answers, e.g.
 * { "answers": [ {"id": 901, "option_id": 2, "note": "Cracked"}, {"id": 902, "value": 60},
 *                {"id": 950, "text": "Roof rails"}, {"id": 903, "clear": true} ] }
 *
 * Each row sends only the field its item's type uses.
 */
class SaveAnswersRequest extends FormRequest
{
    private ?Inspection $inspection = null;

    /**
     * Only the inspector who accepted the booking (404 for anyone else).
     */
    public function authorize(): bool
    {
        Gate::authorize('work', $this->booking());

        return true;
    }

    protected function prepareForValidation(): void
    {
        if (! is_array($this->input('answers'))) {
            return;
        }

        $this->merge(['answers' => array_map(function ($row) {
            foreach (['text', 'note'] as $field) {
                if (is_array($row) && is_string($row[$field] ?? null)) {
                    $row[$field] = Str::squish($row[$field]) ?: null;
                }
            }

            return $row;
        }, $this->input('answers'))]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'answers' => ['required', 'array', 'list', 'min:1', 'max:300'],
            'answers.*' => ['array:id,option_id,value,text,note,clear'],
            'answers.*.id' => ['required', 'integer', 'distinct'],
            'answers.*.option_id' => ['nullable', 'integer'],
            'answers.*.value' => ['nullable', 'numeric'],
            'answers.*.text' => ['nullable', 'string', 'max:1000'],
            'answers.*.note' => ['nullable', 'string', 'max:500'],
            'answers.*.clear' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Check each row against its item: right field for the type, a listed
     * option, a number inside the limits.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $rows = $this->input('answers');
            $answers = $this->inspection()->answers()->whereKey(array_column($rows, 'id'))->get()->keyBy('id');

            foreach ($rows as $index => $row) {
                $answer = $answers->get($row['id']);

                if (! $answer) {
                    $validator->errors()->add("answers.{$index}.id", 'This item is not part of this inspection.');

                    continue;
                }

                foreach ($this->rowErrors($answer, $row) as $field => $message) {
                    $validator->errors()->add("answers.{$index}.{$field}", $message);
                }
            }
        }];
    }

    public function booking(): Booking
    {
        return $this->route('booking');
    }

    public function inspection(): Inspection
    {
        return $this->inspection ??= app(InspectionService::class)->forBooking($this->booking());
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, string>
     */
    private function rowErrors(InspectionAnswer $answer, array $row): array
    {
        $sent = fn (string $field) => isset($row[$field]);

        if (filter_var($row['clear'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            return collect(['option_id', 'value', 'text', 'note'])
                ->filter($sent)
                ->mapWithKeys(fn (string $field) => [$field => 'Not used when clearing an answer.'])
                ->all();
        }

        $type = $answer->answer_type;
        $field = match ($type) {
            AnswerType::Choice => 'option_id',
            AnswerType::Number, AnswerType::Percentage => 'value',
            AnswerType::Text => 'text',
        };

        $errors = collect(['option_id', 'value', 'text'])
            ->reject(fn (string $other) => $other === $field)
            ->filter($sent)
            ->mapWithKeys(fn (string $other) => [$other => "Not used for {$type->value} items."])
            ->all();

        if (! $sent($field)) {
            return [...$errors, $field => match ($type) {
                AnswerType::Choice => 'Select one of the listed options.',
                AnswerType::Text => 'Enter a note.',
                default => 'Enter a value.',
            }];
        }

        $problem = match ($type) {
            AnswerType::Choice => $answer->option((int) $row['option_id']) ? null : 'Select one of the listed options.',
            AnswerType::Number => $this->rangeError((float) $row['value'], $answer->min_value, $answer->max_value),
            AnswerType::Percentage => $this->rangeError((float) $row['value'], 0, 100),
            AnswerType::Text => null,
        };

        return $problem ? [...$errors, $field => $problem] : $errors;
    }

    private function rangeError(float $value, ?float $min, ?float $max): ?string
    {
        $format = fn (float $number) => rtrim(rtrim(number_format($number, 2, '.', ''), '0'), '.');

        return match (true) {
            $min !== null && $max !== null && ($value < $min || $value > $max) => "Must be between {$format($min)} and {$format($max)}.",
            $min !== null && $value < $min => "Must be at least {$format($min)}.",
            $max !== null && $value > $max => "Must be at most {$format($max)}.",
            default => null,
        };
    }
}
