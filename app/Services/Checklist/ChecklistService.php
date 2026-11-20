<?php

namespace App\Services\Checklist;

use App\Enums\AnswerType;
use App\Models\ChecklistAnswerOption;
use App\Models\ChecklistAnswerSet;
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
            $item = new ChecklistItem($this->answerFields(AnswerType::from($data['answer_type']), $data));
            $item->checklist_section_id = $section->id;
            $item->sort_order = $this->nextItemPosition($section);
            $item->save();

            return $item->refresh();
        });
    }

    /**
     * Edit an item. If it moves to another section, it goes to the end there.
     * Changing the answer type clears the settings the new type doesn't use.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateItem(ChecklistItem $item, array $data): ChecklistItem
    {
        return DB::transaction(function () use ($item, $data) {
            $type = isset($data['answer_type']) ? AnswerType::from($data['answer_type']) : $item->answer_type;
            $item->fill($this->answerFields($type, $data));

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

    /**
     * Create an answer list with its options, in the order sent.
     *
     * @param  array{name: string, options: list<array{label: string, outcome: string}>}  $data
     */
    public function createAnswerSet(array $data): ChecklistAnswerSet
    {
        return DB::transaction(function () use ($data) {
            $set = ChecklistAnswerSet::create(['name' => $data['name']]);

            foreach (array_values($data['options']) as $position => $option) {
                $this->saveOption($set, $option, $position + 1);
            }

            return $set->refresh();
        });
    }

    /**
     * Rename, hide or show an answer list.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException When hiding a list that active items still use.
     */
    public function updateAnswerSet(ChecklistAnswerSet $set, array $data): ChecklistAnswerSet
    {
        return DB::transaction(function () use ($set, $data) {
            if (isset($data['is_active']) && ! (bool) $data['is_active'] && $set->is_active) {
                $inUse = $set->items()->where('is_active', true)->count();

                if ($inUse > 0) {
                    throw ValidationException::withMessages([
                        'is_active' => ["This list is used by {$inUse} active ".str('item')->plural($inUse).'. Change them first.'],
                    ]);
                }
            }

            $set->update($data);

            return $set;
        });
    }

    /**
     * @param  array{label: string, outcome: string}  $data
     */
    public function createAnswerOption(ChecklistAnswerSet $set, array $data): ChecklistAnswerOption
    {
        return DB::transaction(function () use ($set, $data) {
            $position = (int) $set->options()->lockForUpdate()->max('sort_order') + 1;

            return $this->saveOption($set, $data, $position)->refresh();
        });
    }

    /**
     * Edit, hide or show an option.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException When hiding the list's last active option.
     */
    public function updateAnswerOption(ChecklistAnswerOption $option, array $data): ChecklistAnswerOption
    {
        return DB::transaction(function () use ($option, $data) {
            if (isset($data['is_active']) && ! (bool) $data['is_active'] && $option->is_active) {
                $othersActive = ChecklistAnswerOption::query()
                    ->where('checklist_answer_set_id', $option->checklist_answer_set_id)
                    ->whereKeyNot($option->id)
                    ->where('is_active', true)
                    ->lockForUpdate()
                    ->count();

                if ($othersActive === 0) {
                    throw ValidationException::withMessages([
                        'is_active' => ['A list needs at least one active option.'],
                    ]);
                }
            }

            $option->update($data);

            return $option;
        });
    }

    /**
     * @param  list<int>  $ids  Every option id of this list, in the new order.
     *
     * @throws ValidationException When the ids aren't exactly the list's options.
     */
    public function reorderAnswerOptions(ChecklistAnswerSet $set, array $ids): void
    {
        DB::transaction(function () use ($set, $ids) {
            $existing = $set->options()->lockForUpdate()->pluck('id')->all();
            $this->assertSameSet($ids, $existing, 'option');

            foreach (array_values($ids) as $position => $id) {
                ChecklistAnswerOption::whereKey($id)->update(['sort_order' => $position + 1]);
            }
        });
    }

    /**
     * @param  array{label: string, outcome: string}  $data
     */
    private function saveOption(ChecklistAnswerSet $set, array $data, int $position): ChecklistAnswerOption
    {
        $option = new ChecklistAnswerOption(['label' => $data['label'], 'outcome' => $data['outcome']]);
        $option->checklist_answer_set_id = $set->id;
        $option->sort_order = $position;
        $option->save();

        return $option;
    }

    /**
     * Keep only the answer settings the type uses; the rest become null.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function answerFields(AnswerType $type, array $data): array
    {
        $unused = match ($type) {
            AnswerType::Choice => ['unit', 'min_value', 'max_value', 'problem_below', 'problem_above'],
            AnswerType::Number => ['checklist_answer_set_id'],
            AnswerType::Percentage => ['checklist_answer_set_id', 'unit', 'min_value', 'max_value'],
            AnswerType::Text => ['checklist_answer_set_id', 'unit', 'min_value', 'max_value', 'problem_below', 'problem_above'],
        };

        return [...$data, ...array_fill_keys($unused, null)];
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
