<?php

namespace Database\Seeders;

use App\Enums\AnswerOutcome;
use App\Models\ChecklistAnswerOption;
use App\Models\ChecklistAnswerSet;
use Illuminate\Database\Seeder;

/**
 * Ready-made answer lists for "pick one" checklist items. Safe to run
 * again: lists and options that exist are left alone (one an admin hid
 * stays hidden).
 *
 * php artisan db:seed --class=ChecklistAnswerSetSeeder
 */
class ChecklistAnswerSetSeeder extends Seeder
{
    /**
     * @var array<string, array<string, AnswerOutcome>>
     */
    public const SETS = [
        'Condition' => [
            'Good' => AnswerOutcome::Ok,
            'Bad' => AnswerOutcome::Problem,
            'None' => AnswerOutcome::NotApplicable,
            'Not present' => AnswerOutcome::NotApplicable,
        ],
        'Working' => [
            'Functioning' => AnswerOutcome::Ok,
            'Not functioning' => AnswerOutcome::Problem,
            'None' => AnswerOutcome::NotApplicable,
        ],
        'Fault present' => [
            'No' => AnswerOutcome::Ok,
            'Yes' => AnswerOutcome::Problem,
        ],
        'Yes / No' => [
            'Yes' => AnswerOutcome::Info,
            'No' => AnswerOutcome::Info,
        ],
        'Presence' => [
            'Not present' => AnswerOutcome::Ok,
            'Present' => AnswerOutcome::Problem,
        ],
        'Accidents' => [
            'None' => AnswerOutcome::Ok,
            'Non structural' => AnswerOutcome::Info,
            'Structural' => AnswerOutcome::Problem,
        ],
        'Tappets' => [
            'Normal' => AnswerOutcome::Ok,
            'Noisy' => AnswerOutcome::Problem,
        ],
        'Gear engagement' => [
            'Smooth' => AnswerOutcome::Ok,
            'Rough' => AnswerOutcome::Problem,
            'Slipping' => AnswerOutcome::Problem,
        ],
        'Rating' => [
            'Excellent' => AnswerOutcome::Ok,
            'Good' => AnswerOutcome::Ok,
            'Medium' => AnswerOutcome::Ok,
            'Poor' => AnswerOutcome::Problem,
        ],
        'Wheel type' => [
            'Steel' => AnswerOutcome::Info,
            'Alloy' => AnswerOutcome::Info,
        ],
        'Roof' => [
            'Standard' => AnswerOutcome::Info,
            'Sunroof' => AnswerOutcome::Info,
            'Panoramic' => AnswerOutcome::Info,
        ],
        'Roof mechanism' => [
            'None' => AnswerOutcome::Info,
            'Manual' => AnswerOutcome::Info,
            'Electric' => AnswerOutcome::Info,
        ],
        'Drive system' => [
            'FF' => AnswerOutcome::Info,
            'FR' => AnswerOutcome::Info,
            'MR' => AnswerOutcome::Info,
            'RR' => AnswerOutcome::Info,
            '4WD' => AnswerOutcome::Info,
            'AWD' => AnswerOutcome::Info,
        ],
        'Interior type' => [
            'Cloth' => AnswerOutcome::Info,
            'Leather' => AnswerOutcome::Info,
            'Part leather' => AnswerOutcome::Info,
            'Vinyl' => AnswerOutcome::Info,
        ],
        'Seat type' => [
            'Manual' => AnswerOutcome::Info,
            'Power' => AnswerOutcome::Info,
        ],
        'Side mirror type' => [
            'Manual' => AnswerOutcome::Info,
            'Power' => AnswerOutcome::Info,
            'Power + folding' => AnswerOutcome::Info,
        ],
    ];

    public function run(): void
    {
        foreach (self::SETS as $name => $options) {
            $set = ChecklistAnswerSet::firstOrCreate(['name' => $name]);
            $position = (int) $set->options()->max('sort_order');

            foreach ($options as $label => $outcome) {
                if ($set->options()->where('label', $label)->exists()) {
                    continue;
                }

                $option = new ChecklistAnswerOption(['label' => $label, 'outcome' => $outcome]);
                $option->checklist_answer_set_id = $set->id;
                $option->sort_order = ++$position;
                $option->save();
            }
        }
    }
}
