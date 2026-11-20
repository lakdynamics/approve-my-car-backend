<?php

namespace Tests\Feature;

use App\Enums\AnswerType;
use App\Models\ChecklistItem;
use App\Models\ChecklistSection;
use Database\Seeders\ChecklistAnswerSetSeeder;
use Database\Seeders\ChecklistTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChecklistTemplateSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_loads_the_checklist_and_can_run_again(): void
    {
        $this->seed(ChecklistTemplateSeeder::class);
        $this->seed(ChecklistTemplateSeeder::class);

        $this->assertSame(array_keys(ChecklistTemplateSeeder::SECTIONS), ChecklistSection::ordered()->pluck('name')->all());

        foreach (ChecklistTemplateSeeder::SECTIONS as $name => $items) {
            $this->assertSame(
                array_keys($items),
                ChecklistSection::where('name', $name)->sole()->items()->pluck('label')->all()
            );
        }

        $odo = ChecklistItem::where('label', 'ODO Reading')->sole();
        $this->assertSame(AnswerType::Number, $odo->answer_type);
        $this->assertSame('km', $odo->unit);

        $tyre = ChecklistItem::where('label', 'Front Left Tyre Wear')->sole();
        $this->assertSame(AnswerType::Percentage, $tyre->answer_type);
        $this->assertSame(80.0, $tyre->problem_above);

        $misfiring = ChecklistItem::where('label', 'Engine Misfiring')->sole();
        $this->assertSame(AnswerType::Choice, $misfiring->answer_type);
        $this->assertSame('Fault present', $misfiring->answerSet->name);
    }

    public function test_admin_changes_are_kept_on_a_rerun(): void
    {
        $this->seed(ChecklistTemplateSeeder::class);
        $turbo = ChecklistItem::where('label', 'Turbo')->sole();
        $turbo->update(['is_active' => false]);

        $this->seed(ChecklistTemplateSeeder::class);

        $this->assertFalse($turbo->fresh()->is_active);
        $this->assertSame(1, ChecklistItem::where('label', 'Turbo')->count());
    }

    public function test_every_answer_list_it_uses_exists(): void
    {
        $used = collect(ChecklistTemplateSeeder::SECTIONS)->flatMap(fn (array $items) => array_values($items))->filter(fn ($answer) => is_string($answer))->unique();

        $this->assertNotEmpty($used);
        $this->assertEmpty($used->diff(array_keys(ChecklistAnswerSetSeeder::SETS))->all());
    }
}
