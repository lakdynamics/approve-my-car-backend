<?php

namespace Database\Factories;

use App\Enums\AnswerOutcome;
use App\Enums\AnswerType;
use App\Models\InspectionAnswer;
use App\Models\InspectionSection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InspectionAnswer>
 */
class InspectionAnswerFactory extends Factory
{
    /**
     * An unanswered "pick one" item with Good / Bad / None options.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'inspection_section_id' => InspectionSection::factory(),
            'inspection_id' => fn (array $attributes) => InspectionSection::findOrFail($attributes['inspection_section_id'])->inspection_id,
            'label' => fake()->words(3, true),
            'answer_type' => AnswerType::Choice,
            'options' => [
                ['id' => 1, 'label' => 'Good', 'outcome' => AnswerOutcome::Ok->value],
                ['id' => 2, 'label' => 'Bad', 'outcome' => AnswerOutcome::Problem->value],
                ['id' => 3, 'label' => 'None', 'outcome' => AnswerOutcome::NotApplicable->value],
            ],
            'sort_order' => fake()->numberBetween(1, 50),
        ];
    }

    public function text(): static
    {
        return $this->state(fn (array $attributes) => ['answer_type' => AnswerType::Text, 'options' => null]);
    }
}
