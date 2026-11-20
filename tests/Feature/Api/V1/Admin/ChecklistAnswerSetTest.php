<?php

namespace Tests\Feature\Api\V1\Admin;

use App\Models\ChecklistAnswerOption;
use App\Models\ChecklistAnswerSet;
use App\Models\ChecklistItem;
use App\Models\User;
use Database\Seeders\ChecklistAnswerSetSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ChecklistAnswerSetTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{string, string}>
     */
    public static function routes(): array
    {
        return [
            'list' => ['get', '/api/v1/admin/checklist/answer-sets'],
            'add list' => ['post', '/api/v1/admin/checklist/answer-sets'],
            'edit list' => ['patch', '/api/v1/admin/checklist/answer-sets/{set}'],
            'add option' => ['post', '/api/v1/admin/checklist/answer-sets/{set}/options'],
            'order options' => ['put', '/api/v1/admin/checklist/answer-sets/{set}/options/order'],
            'edit option' => ['patch', '/api/v1/admin/checklist/answer-options/{option}'],
        ];
    }

    #[DataProvider('routes')]
    public function test_only_admins_can_manage_answer_lists(string $method, string $uri): void
    {
        $option = ChecklistAnswerOption::factory()->create();
        $uri = str_replace(['{set}', '{option}'], [$option->checklist_answer_set_id, $option->id], $uri);

        Sanctum::actingAs(User::factory()->create());
        $this->json($method, $uri)->assertForbidden();

        Sanctum::actingAs(User::factory()->inspector()->create());
        $this->json($method, $uri)->assertForbidden();
    }

    public function test_admin_creates_and_lists_answer_lists(): void
    {
        $this->actingAsAdmin();

        $id = $this->postJson('/api/v1/admin/checklist/answer-sets', [
            'name' => '  Drive  system ',
            'options' => [
                ['label' => 'FF', 'outcome' => 'info'],
                ['label' => ' 4WD ', 'outcome' => 'info'],
            ],
        ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Drive system')
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.items_count', 0)
            ->assertJsonPath('data.options.0.label', 'FF')
            ->assertJsonPath('data.options.1.label', '4WD')
            ->assertJsonPath('data.options.1.sort_order', 2)
            ->json('data.id');

        ChecklistItem::factory()->choice(ChecklistAnswerSet::find($id))->create();
        ChecklistAnswerSet::factory()->hidden()->create(['name' => 'Old list']);

        $this->getJson('/api/v1/admin/checklist/answer-sets')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.items_count', 1)
            ->assertJsonPath('data.0.options.1.outcome', 'info');

        $this->getJson('/api/v1/admin/checklist/answer-sets?include_inactive=1')->assertJsonCount(2, 'data');
    }

    public function test_duplicates_and_empty_lists_are_rejected(): void
    {
        $this->actingAsAdmin();
        $set = ChecklistAnswerSet::factory()->withOptions()->create(['name' => 'Condition']);
        $uri = '/api/v1/admin/checklist/answer-sets';

        $this->postJson($uri, ['name' => 'condition', 'options' => [['label' => 'A', 'outcome' => 'ok']]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name' => 'An answer list with this name already exists.']);

        $this->postJson($uri, ['name' => 'Lights', 'options' => []])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['options' => 'Add at least one option.']);

        $this->postJson($uri, ['name' => 'Lights', 'options' => [['label' => 'On', 'outcome' => 'ok'], ['label' => 'on', 'outcome' => 'maybe']]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['options.1.label' => 'Each option needs a different label.', 'options.1.outcome']);

        $this->postJson("{$uri}/{$set->id}/options", ['label' => 'good', 'outcome' => 'ok'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['label' => 'This list already has this option.']);
    }

    public function test_admin_adds_edits_and_reorders_options(): void
    {
        $this->actingAsAdmin();
        $set = ChecklistAnswerSet::factory()->withOptions()->create();
        [$good, $bad, $none] = $set->options()->get()->all();

        $worn = $this->postJson("/api/v1/admin/checklist/answer-sets/{$set->id}/options", ['label' => 'Worn', 'outcome' => 'problem'])
            ->assertCreated()
            ->assertJsonPath('data.sort_order', 4)
            ->assertJsonPath('data.is_active', true)
            ->json('data.id');

        $this->patchJson("/api/v1/admin/checklist/answer-options/{$none->id}", ['label' => 'Not fitted', 'outcome' => 'info'])
            ->assertOk()
            ->assertJsonPath('data.label', 'Not fitted')
            ->assertJsonPath('data.outcome', 'info');

        $this->putJson("/api/v1/admin/checklist/answer-sets/{$set->id}/options/order", ['ids' => [$worn, $bad->id, $good->id, $none->id]])
            ->assertOk()
            ->assertJsonPath('data.options.0.label', 'Worn')
            ->assertJsonPath('data.options.3.label', 'Not fitted');

        $foreign = ChecklistAnswerOption::factory()->create();
        $this->putJson("/api/v1/admin/checklist/answer-sets/{$set->id}/options/order", ['ids' => [$worn, $bad->id, $good->id, $foreign->id]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['ids' => 'Send every option id exactly once.']);
    }

    public function test_a_list_keeps_one_active_option(): void
    {
        $this->actingAsAdmin();
        $set = ChecklistAnswerSet::factory()->create();
        $first = ChecklistAnswerOption::factory()->for($set, 'set')->create();
        $second = ChecklistAnswerOption::factory()->for($set, 'set')->create();

        $this->patchJson("/api/v1/admin/checklist/answer-options/{$first->id}", ['is_active' => false])->assertOk();

        $this->patchJson("/api/v1/admin/checklist/answer-options/{$second->id}", ['is_active' => 0])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['is_active' => 'A list needs at least one active option.']);
    }

    public function test_a_list_used_by_active_items_cannot_be_hidden(): void
    {
        $this->actingAsAdmin();
        $set = ChecklistAnswerSet::factory()->withOptions()->create();
        $item = ChecklistItem::factory()->choice($set)->create();

        $this->patchJson("/api/v1/admin/checklist/answer-sets/{$set->id}", ['is_active' => false])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['is_active' => 'This list is used by 1 active item. Change them first.']);

        $item->update(['is_active' => false]);

        $this->patchJson("/api/v1/admin/checklist/answer-sets/{$set->id}", ['name' => 'Retired', 'is_active' => false])
            ->assertOk()
            ->assertJsonPath('data.name', 'Retired')
            ->assertJsonPath('data.is_active', false);
    }

    public function test_seeder_can_run_again_without_duplicates(): void
    {
        $this->seed(ChecklistAnswerSetSeeder::class);
        $this->seed(ChecklistAnswerSetSeeder::class);

        $this->assertSame(count(ChecklistAnswerSetSeeder::SETS), ChecklistAnswerSet::count());
        $this->assertSame(
            ['Good', 'Bad', 'None', 'Not present'],
            ChecklistAnswerSet::where('name', 'Condition')->sole()->options()->pluck('label')->all()
        );
    }

    private function actingAsAdmin(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
    }
}
