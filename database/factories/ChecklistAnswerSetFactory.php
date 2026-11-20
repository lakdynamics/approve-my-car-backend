<?php

namespace Database\Factories;

use App\Enums\AnswerOutcome;
use App\Models\ChecklistAnswerOption;
use App\Models\ChecklistAnswerSet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChecklistAnswerSet>
 */
class ChecklistAnswerSetFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => ucfirst(fake()->unique()->words(2, true)),
            'is_active' => true,
        ];
    }

    public function hidden(): static
    {
        return $this->state(['is_active' => false]);
    }

    /**
     * Good (ok), Bad (problem), None (not applicable).
     */
    public function withOptions(): static
    {
        return $this->afterCreating(function (ChecklistAnswerSet $set) {
            ChecklistAnswerOption::factory()
                ->for($set, 'set')
                ->count(3)
                ->sequence(
                    ['label' => 'Good', 'outcome' => AnswerOutcome::Ok, 'sort_order' => 1],
                    ['label' => 'Bad', 'outcome' => AnswerOutcome::Problem, 'sort_order' => 2],
                    ['label' => 'None', 'outcome' => AnswerOutcome::NotApplicable, 'sort_order' => 3],
                )
                ->create();
        });
    }
}
