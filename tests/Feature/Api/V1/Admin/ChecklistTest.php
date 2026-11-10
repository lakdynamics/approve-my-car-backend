<?php

namespace Tests\Feature\Api\V1\Admin;

use App\Models\ChecklistItem;
use App\Models\ChecklistSection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ChecklistTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{string, string}>
     */
    public static function routes(): array
    {
        return [
            'list' => ['get', '/api/v1/admin/checklist'],
            'add section' => ['post', '/api/v1/admin/checklist/sections'],
            'edit section' => ['patch', '/api/v1/admin/checklist/sections/{section}'],
            'order sections' => ['put', '/api/v1/admin/checklist/sections/order'],
            'add item' => ['post', '/api/v1/admin/checklist/sections/{section}/items'],
            'order items' => ['put', '/api/v1/admin/checklist/sections/{section}/items/order'],
            'edit item' => ['patch', '/api/v1/admin/checklist/items/{item}'],
        ];
    }

    #[DataProvider('routes')]
    public function test_only_admins_can_manage_the_checklist(string $method, string $uri): void
    {
        $item = ChecklistItem::factory()->create();
        $uri = str_replace(['{section}', '{item}'], [$item->checklist_section_id, $item->id], $uri);

        Sanctum::actingAs(User::factory()->create());
        $this->json($method, $uri)->assertForbidden();

        Sanctum::actingAs(User::factory()->inspector()->create());
        $this->json($method, $uri)->assertForbidden();
    }

    public function test_admin_builds_a_checklist(): void
    {
        $this->actingAsAdmin();

        $engine = $this->postJson('/api/v1/admin/checklist/sections', ['name' => '  Engine  bay ', 'description' => 'Under the bonnet'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Engine bay')
            ->assertJsonPath('data.sort_order', 1)
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.items', [])
            ->json('data.id');

        $this->postJson('/api/v1/admin/checklist/sections', ['name' => 'Exterior'])
            ->assertCreated()
            ->assertJsonPath('data.sort_order', 2);

        $this->postJson("/api/v1/admin/checklist/sections/{$engine}/items", ['label' => 'Oil level', 'description' => 'Check dipstick'])
            ->assertCreated()
            ->assertJsonPath('data.label', 'Oil level')
            ->assertJsonPath('data.checklist_section_id', $engine)
            ->assertJsonPath('data.sort_order', 1);

        $this->postJson("/api/v1/admin/checklist/sections/{$engine}/items", ['label' => 'Coolant level'])
            ->assertJsonPath('data.sort_order', 2);

        $this->getJson('/api/v1/admin/checklist')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'Engine bay')
            ->assertJsonPath('data.0.items.0.label', 'Oil level')
            ->assertJsonPath('data.0.items.1.label', 'Coolant level')
            ->assertJsonPath('data.1.name', 'Exterior');
    }

    public function test_duplicate_names_and_labels_are_rejected(): void
    {
        $this->actingAsAdmin();
        $engine = ChecklistSection::factory()->create(['name' => 'Engine']);
        $exterior = ChecklistSection::factory()->create(['name' => 'Exterior']);
        ChecklistItem::factory()->for($engine, 'section')->create(['label' => 'Leaks']);

        $this->postJson('/api/v1/admin/checklist/sections', ['name' => 'engine'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name' => 'A section with this name already exists.']);

        $this->postJson("/api/v1/admin/checklist/sections/{$engine->id}/items", ['label' => 'Leaks'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['label' => 'This section already has an item with this label.']);

        // The same label in another section is fine.
        $this->postJson("/api/v1/admin/checklist/sections/{$exterior->id}/items", ['label' => 'Leaks'])->assertCreated();
    }

    public function test_admin_edits_and_hides(): void
    {
        $this->actingAsAdmin();
        $section = ChecklistSection::factory()->create(['name' => 'Interior']);
        $other = ChecklistSection::factory()->create(['name' => 'Tyres']);
        $item = ChecklistItem::factory()->for($section, 'section')->create(['label' => 'Seats']);
        ChecklistItem::factory()->for($other, 'section')->create();

        $this->patchJson("/api/v1/admin/checklist/sections/{$section->id}", ['name' => 'Cabin', 'description' => 'Inside'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Cabin')
            ->assertJsonPath('data.description', 'Inside');

        $this->patchJson("/api/v1/admin/checklist/items/{$item->id}", ['label' => 'Seats and belts', 'is_active' => false])
            ->assertOk()
            ->assertJsonPath('data.label', 'Seats and belts')
            ->assertJsonPath('data.is_active', false);

        $this->patchJson("/api/v1/admin/checklist/sections/{$other->id}", ['is_active' => false])->assertOk();

        // Hidden sections and items are left out unless asked for.
        $this->getJson('/api/v1/admin/checklist')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Cabin')
            ->assertJsonCount(0, 'data.0.items');

        $this->getJson('/api/v1/admin/checklist?include_inactive=1')
            ->assertJsonCount(2, 'data')
            ->assertJsonCount(1, 'data.0.items')
            ->assertJsonCount(1, 'data.1.items');
    }

    public function test_moving_an_item_puts_it_at_the_end_of_the_new_section(): void
    {
        $this->actingAsAdmin();
        $from = ChecklistSection::factory()->create();
        $to = ChecklistSection::factory()->create();
        $item = ChecklistItem::factory()->for($from, 'section')->create(['label' => 'Horn']);
        ChecklistItem::factory()->for($to, 'section')->count(2)->sequence(['sort_order' => 1], ['sort_order' => 2])->create();

        $this->patchJson("/api/v1/admin/checklist/items/{$item->id}", ['checklist_section_id' => $to->id])
            ->assertOk()
            ->assertJsonPath('data.checklist_section_id', $to->id)
            ->assertJsonPath('data.sort_order', 3);

        // A label that already exists in the target section blocks the move.
        $clash = ChecklistItem::factory()->for($from, 'section')->create(['label' => 'Horn']);
        $this->patchJson("/api/v1/admin/checklist/items/{$clash->id}", ['checklist_section_id' => $to->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('label');

        $this->patchJson("/api/v1/admin/checklist/items/{$clash->id}", ['checklist_section_id' => 999999])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['checklist_section_id' => 'Select an existing section.']);
    }

    public function test_reordering_sections_and_items(): void
    {
        $this->actingAsAdmin();
        [$a, $b, $c] = ChecklistSection::factory()->count(3)->create()->all();
        $c->update(['is_active' => false]);
        [$x, $y] = ChecklistItem::factory()->for($a, 'section')->count(2)->create()->all();

        $this->putJson('/api/v1/admin/checklist/sections/order', ['ids' => [$c->id, $a->id, $b->id]])->assertOk();
        // Hidden sections keep their place too.
        $this->assertSame([2, 3, 1], [$a->fresh()->sort_order, $b->fresh()->sort_order, $c->fresh()->sort_order]);

        $this->putJson("/api/v1/admin/checklist/sections/{$a->id}/items/order", ['ids' => [$y->id, $x->id]])
            ->assertOk()
            ->assertJsonPath('data.items.0.id', $y->id)
            ->assertJsonPath('data.items.1.id', $x->id);
    }

    public function test_reorder_must_send_every_id_exactly_once(): void
    {
        $this->actingAsAdmin();
        [$a, $b] = ChecklistSection::factory()->count(2)->create()->all();
        $item = ChecklistItem::factory()->for($a, 'section')->create();
        $foreign = ChecklistItem::factory()->for($b, 'section')->create();

        foreach ([[$a->id], [$a->id, $a->id], [$a->id, $b->id, 999]] as $ids) {
            $this->putJson('/api/v1/admin/checklist/sections/order', ['ids' => $ids])
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['ids' => 'Send every section id exactly once.']);
        }

        $this->putJson("/api/v1/admin/checklist/sections/{$a->id}/items/order", ['ids' => [$item->id, $foreign->id]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['ids' => 'Send every item id exactly once.']);
    }

    private function actingAsAdmin(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
    }
}
