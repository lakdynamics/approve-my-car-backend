<?php

namespace Database\Factories;

use App\Enums\AnswerOutcome;
use App\Models\ChecklistAnswerOption;
use App\Models\ChecklistAnswerSet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChecklistAnswerOption>
 */
class ChecklistAnswerOptionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'checklist_answer_set_id' => ChecklistAnswerSet::factory(),
            'label' => ucfirst(fake()->unique()->word()),
            'outcome' => AnswerOutcome::Ok,
            'sort_order' => fn (array $attributes) => (int) ChecklistAnswerOption::where('checklist_answer_set_id', $attributes['checklist_answer_set_id'])->max('sort_order') + 1,
            'is_active' => true,
        ];
    }

    public function hidden(): static
    {
        return $this->state(['is_active' => false]);
    }
}
