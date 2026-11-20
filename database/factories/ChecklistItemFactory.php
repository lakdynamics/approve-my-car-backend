<?php

namespace Database\Factories;

use App\Enums\AnswerType;
use App\Models\ChecklistAnswerSet;
use App\Models\ChecklistItem;
use App\Models\ChecklistSection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChecklistItem>
 */
class ChecklistItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'checklist_section_id' => ChecklistSection::factory(),
            'label' => ucfirst(fake()->unique()->words(3, true)),
            'description' => null,
            'sort_order' => fn (array $attributes) => (int) ChecklistItem::where('checklist_section_id', $attributes['checklist_section_id'])->max('sort_order') + 1,
            'is_active' => true,
            'answer_type' => AnswerType::Text,
        ];
    }

    public function hidden(): static
    {
        return $this->state(['is_active' => false]);
    }

    /**
     * A "pick one" item. Without a list, one with Good / Bad / None is made.
     */
    public function choice(?ChecklistAnswerSet $set = null): static
    {
        return $this->state(fn () => [
            'answer_type' => AnswerType::Choice,
            'checklist_answer_set_id' => $set?->id ?? ChecklistAnswerSet::factory()->withOptions(),
        ]);
    }

    public function number(): static
    {
        return $this->state([
            'answer_type' => AnswerType::Number,
            'unit' => 'km',
            'min_value' => 0,
        ]);
    }

    public function percentage(): static
    {
        return $this->state(['answer_type' => AnswerType::Percentage]);
    }
}
