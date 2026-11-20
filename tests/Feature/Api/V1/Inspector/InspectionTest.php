<?php

namespace Tests\Feature\Api\V1\Inspector;

use App\Models\Booking;
use App\Models\ChecklistAnswerSet;
use App\Models\ChecklistItem;
use App\Models\ChecklistSection;
use App\Models\InspectionAnswer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The inspector fills in the checklist copied when the booking started.
 */
class InspectionTest extends TestCase
{
    use RefreshDatabase;

    private User $inspector;

    private Booking $booking;

    /** @var array<string, ChecklistItem> */
    private array $items;

    protected function setUp(): void
    {
        parent::setUp();

        // 06:00 UTC = 11:30 in Colombo on 10 Nov.
        $this->travelTo('2026-11-10 06:00:00');

        $engine = ChecklistSection::factory()->create(['name' => 'Engine']);
        $tyres = ChecklistSection::factory()->create(['name' => 'Tyres']);
        ChecklistSection::factory()->create(['name' => 'Empty']);
        ChecklistItem::factory()->choice()->for(ChecklistSection::factory()->hidden()->create(['name' => 'Retired']), 'section')->create();

        $condition = ChecklistAnswerSet::factory()->withOptions()->create(['name' => 'Condition']);
        $condition->options()->where('label', 'None')->update(['is_active' => false]);

        $this->items = [
            'filter' => ChecklistItem::factory()->choice($condition)->for($engine, 'section')->create(['label' => 'Air Filter']),
            'odo' => ChecklistItem::factory()->number()->for($engine, 'section')->create(['label' => 'ODO Reading', 'max_value' => 2000000]),
            'extras' => ChecklistItem::factory()->for($engine, 'section')->create(['label' => 'Extras']),
            'old' => ChecklistItem::factory()->hidden()->for($engine, 'section')->create(['label' => 'Old item']),
            'tyre' => ChecklistItem::factory()->percentage()->for($tyres, 'section')->create(['label' => 'Front Left Tyre Wear', 'problem_above' => 80]),
        ];

        $this->inspector = User::factory()->inspector()->create();
        Sanctum::actingAs($this->inspector);

        $this->booking = Booking::factory()->accepted($this->inspector)->create(['scheduled_date' => '2026-11-10']);
    }

    public function test_starting_copies_the_active_checklist(): void
    {
        $this->getJson($this->url())
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Start the inspection first.');

        $this->start();

        $this->getJson($this->url())
            ->assertOk()
            ->assertJsonPath('data.booking_id', $this->booking->id)
            ->assertJsonPath('data.progress', ['answered' => 0, 'required' => 3, 'required_answered' => 0, 'total' => 4, 'problems' => 0])
            ->assertJsonCount(2, 'data.sections')
            ->assertJsonPath('data.sections.0.name', 'Engine')
            ->assertJsonPath('data.sections.0.items.*.label', ['Air Filter', 'ODO Reading', 'Extras'])
            ->assertJsonPath('data.sections.0.items.0.options.*.label', ['Good', 'Bad'])
            ->assertJsonPath('data.sections.0.items.0.answer', null)
            ->assertJsonPath('data.sections.0.items.1.unit', 'km')
            ->assertJsonPath('data.sections.0.items.2.required', false)
            ->assertJsonPath('data.sections.1.items.0.problem_above', 80);
    }

    public function test_admin_edits_do_not_change_a_started_inspection(): void
    {
        $this->start();

        $this->items['filter']->update(['label' => 'Renamed']);
        $this->items['filter']->answerSet->options()->where('label', 'Good')->update(['label' => 'Fine']);
        $this->items['tyre']->update(['problem_above' => 50]);
        ChecklistItem::factory()->for($this->items['odo']->section, 'section')->create();

        $this->getJson($this->url())
            ->assertJsonPath('data.progress.total', 4)
            ->assertJsonPath('data.sections.0.items.0.label', 'Air Filter')
            ->assertJsonPath('data.sections.0.items.0.options.0.label', 'Good')
            ->assertJsonPath('data.sections.1.items.0.problem_above', 80);
    }

    public function test_only_the_assigned_inspector_can_fill_it_in(): void
    {
        $this->start();
        $section = $this->sectionId(0);

        Sanctum::actingAs(User::factory()->inspector()->create());
        $this->getJson($this->url())->assertNotFound();
        $this->patchJson($this->url('/answers'), ['answers' => [['id' => 1, 'value' => 1]]])->assertNotFound();
        $this->patchJson($this->url("/sections/{$section}"), ['comment' => 'x'])->assertNotFound();

        Sanctum::actingAs(User::factory()->create());
        $this->getJson($this->url())->assertForbidden();
    }

    public function test_saving_answers_works_out_the_outcomes(): void
    {
        $this->start();
        [$filter, $odo, $extras, $tyre] = $this->answerIds();
        $bad = $this->optionId($filter, 'Bad');

        $this->patchJson($this->url('/answers'), ['answers' => [
            ['id' => $filter, 'option_id' => $bad, 'note' => '  Torn  '],
            ['id' => $odo, 'value' => 42154],
            ['id' => $extras, 'text' => 'Roof rails'],
            ['id' => $tyre, 'value' => 90],
        ]])
            ->assertOk()
            ->assertJsonPath('data.progress.answered', 4)
            ->assertJsonPath('data.progress.problems', 2)
            ->assertJsonPath('data.sections.0.items.0.answer.label', 'Bad')
            ->assertJsonPath('data.sections.0.items.0.answer.note', 'Torn')
            ->assertJsonPath('data.sections.0.items.0.answer.outcome', 'problem')
            ->assertJsonPath('data.sections.0.items.1.answer.value', 42154)
            ->assertJsonPath('data.sections.0.items.1.answer.outcome', 'ok')
            ->assertJsonPath('data.sections.0.items.2.answer.outcome', 'info')
            ->assertJsonPath('data.sections.1.items.0.answer.outcome', 'problem')
            ->assertJsonPath('data.sections.1.problems', 1);

        $this->patchJson($this->url('/answers'), ['answers' => [['id' => $tyre, 'value' => 60], ['id' => $filter, 'clear' => true]]])
            ->assertOk()
            ->assertJsonPath('data.sections.1.items.0.answer.outcome', 'ok')
            ->assertJsonPath('data.sections.0.items.0.answer', null)
            ->assertJsonPath('data.progress.problems', 0);
    }

    public function test_answers_are_checked_against_their_item(): void
    {
        $this->start();
        [$filter, $odo, $extras, $tyre] = $this->answerIds();
        $hiddenNone = $this->items['filter']->answerSet->options()->where('label', 'None')->value('id');
        $other = InspectionAnswer::factory()->create();

        $this->patchJson($this->url('/answers'), ['answers' => [
            ['id' => $filter, 'option_id' => $hiddenNone],
            ['id' => $odo, 'value' => -5],
            ['id' => $extras, 'value' => 3],
            ['id' => $tyre, 'value' => 101],
            ['id' => $other->id, 'option_id' => 1],
        ]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'answers.0.option_id' => 'Select one of the listed options.',
                'answers.1.value' => 'Must be between 0 and 2000000.',
                'answers.2.value' => 'Not used for text items.',
                'answers.2.text' => 'Enter a note.',
                'answers.3.value' => 'Must be between 0 and 100.',
                'answers.4.id' => 'This item is not part of this inspection.',
            ]);

        $this->patchJson($this->url('/answers'), ['answers' => [['id' => $odo, 'clear' => true, 'value' => 1]]])
            ->assertJsonValidationErrors(['answers.0.value' => 'Not used when clearing an answer.']);

        $this->assertSame(0, InspectionAnswer::whereNotNull('answered_at')->count());
    }

    public function test_section_comment(): void
    {
        $this->start();
        $section = $this->sectionId(1);

        $this->patchJson($this->url("/sections/{$section}"), ['comment' => ' Tyres  worn unevenly. '])
            ->assertOk()
            ->assertJsonPath('data.comment', 'Tyres worn unevenly.');

        $foreign = InspectionAnswer::factory()->create()->inspection_section_id;
        $this->patchJson($this->url("/sections/{$foreign}"), ['comment' => 'x'])->assertNotFound();
    }

    public function test_complete_needs_every_required_answer_then_locks_it(): void
    {
        $this->start();
        [$filter, $odo, , $tyre] = $this->answerIds();

        $this->patchJson($this->url('/answers'), ['answers' => [['id' => $odo, 'value' => 1000]]]);

        $this->postJson("/api/v1/inspector/bookings/{$this->booking->id}/complete")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['answers' => '2 items are not answered.'])
            ->assertJsonPath('missing.0.label', 'Air Filter')
            ->assertJsonPath('missing.1.section', 'Tyres');

        $this->patchJson($this->url('/answers'), ['answers' => [
            ['id' => $filter, 'option_id' => $this->optionId($filter, 'Good')],
            ['id' => $tyre, 'value' => 40],
        ]])->assertOk();

        $this->postJson("/api/v1/inspector/bookings/{$this->booking->id}/complete")
            ->assertOk()
            ->assertJsonPath('data.status', 'completed');

        $this->getJson($this->url())->assertOk()->assertJsonPath('data.completed_at', now()->toJSON());

        $this->patchJson($this->url('/answers'), ['answers' => [['id' => $odo, 'value' => 5]]])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'This inspection is already completed.');
        $this->patchJson($this->url('/sections/'.$this->sectionId(0)), ['comment' => 'Late'])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'This inspection is already completed.');
    }

    public function test_a_booking_started_before_checklists_gets_one_on_first_use(): void
    {
        $started = Booking::factory()->inProgress($this->inspector)->create(['scheduled_date' => '2026-11-10']);

        $this->getJson("/api/v1/inspector/bookings/{$started->id}/inspection")
            ->assertOk()
            ->assertJsonPath('data.progress.total', 4);
    }

    private function start(): void
    {
        $this->postJson("/api/v1/inspector/bookings/{$this->booking->id}/start")->assertOk();
    }

    private function url(string $path = ''): string
    {
        return "/api/v1/inspector/bookings/{$this->booking->id}/inspection{$path}";
    }

    /**
     * Air Filter, ODO Reading, Extras, Front Left Tyre Wear.
     *
     * @return list<int>
     */
    private function answerIds(): array
    {
        return array_map(
            fn (string $key) => InspectionAnswer::where('checklist_item_id', $this->items[$key]->id)->sole()->id,
            ['filter', 'odo', 'extras', 'tyre'],
        );
    }

    private function optionId(int $answerId, string $label): int
    {
        return collect(InspectionAnswer::findOrFail($answerId)->options)->firstWhere('label', $label)['id'];
    }

    private function sectionId(int $index): int
    {
        return $this->getJson($this->url())->json("data.sections.{$index}.id");
    }
}
