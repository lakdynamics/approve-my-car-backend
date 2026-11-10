<?php

namespace App\Services\Checklist;

use App\Models\ChecklistItem;
use App\Models\ChecklistSection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Keeps the checklist's order consistent: new sections and items go to
 * the end, and reordering always covers the whole list.
 */
class ChecklistService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function createSection(array $data): ChecklistSection
    {
        return DB::transaction(function () use ($data) {
            $section = new ChecklistSection($data);
            $section->sort_order = $this->nextSectionPosition();
            $section->save();

            return $section->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createItem(ChecklistSection $section, array $data): ChecklistItem
    {
        return DB::transaction(function () use ($section, $data) {
            $item = new ChecklistItem($data);
            $item->checklist_section_id = $section->id;
            $item->sort_order = $this->nextItemPosition($section);
            $item->save();

            return $item->refresh();
        });
    }

    /**
     * Edit an item. If it moves to another section, it goes to the end there.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateItem(ChecklistItem $item, array $data): ChecklistItem
    {
        return DB::transaction(function () use ($item, $data) {
            $item->fill($data);

            $targetId = $data['checklist_section_id'] ?? null;

            if ($targetId !== null && (int) $targetId !== $item->checklist_section_id) {
                $target = ChecklistSection::findOrFail($targetId);
                $item->checklist_section_id = $target->id;
                $item->sort_order = $this->nextItemPosition($target);
            }

            $item->save();

            return $item;
        });
    }

    /**
     * @param  list<int>  $ids  Every section id, in the new order.
     *
     * @throws ValidationException When the ids aren't exactly the full set.
     */
    public function reorderSections(array $ids): void
    {
        DB::transaction(function () use ($ids) {
            $existing = ChecklistSection::query()->lockForUpdate()->pluck('id')->all();
            $this->assertSameSet($ids, $existing, 'section');

            foreach (array_values($ids) as $position => $id) {
                ChecklistSection::whereKey($id)->update(['sort_order' => $position + 1]);
            }
        });
    }

    /**
     * @param  list<int>  $ids  Every item id of this section, in the new order.
     *
     * @throws ValidationException When the ids aren't exactly the section's items.
     */
    public function reorderItems(ChecklistSection $section, array $ids): void
    {
        DB::transaction(function () use ($section, $ids) {
            $existing = ChecklistItem::query()
                ->where('checklist_section_id', $section->id)
                ->lockForUpdate()
                ->pluck('id')
                ->all();
            $this->assertSameSet($ids, $existing, 'item');

            foreach (array_values($ids) as $position => $id) {
                ChecklistItem::whereKey($id)->update(['sort_order' => $position + 1]);
            }
        });
    }

    private function nextSectionPosition(): int
    {
        return (int) ChecklistSection::query()->lockForUpdate()->max('sort_order') + 1;
    }

    private function nextItemPosition(ChecklistSection $section): int
    {
        return (int) ChecklistItem::query()
            ->where('checklist_section_id', $section->id)
            ->lockForUpdate()
            ->max('sort_order') + 1;
    }

    /**
     * Hidden ones are included, so their place in the order isn't lost.
     *
     * @param  array<int, mixed>  $sent
     * @param  array<int, int>  $existing
     */
    private function assertSameSet(array $sent, array $existing, string $kind): void
    {
        $sent = array_map('intval', $sent);
        $unique = array_unique($sent);
        sort($unique);
        sort($existing);

        if (count($sent) !== count($unique) || $unique !== $existing) {
            throw ValidationException::withMessages([
                'ids' => ["Send every {$kind} id exactly once."],
            ]);
        }
    }
}
