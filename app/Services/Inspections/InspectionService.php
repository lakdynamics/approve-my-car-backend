<?php

namespace App\Services\Inspections;

use App\Enums\AnswerOutcome;
use App\Enums\AnswerType;
use App\Enums\BookingStatus;
use App\Exceptions\Bookings\InvalidBookingStatusException;
use App\Models\Booking;
use App\Models\ChecklistAnswerOption;
use App\Models\ChecklistItem;
use App\Models\ChecklistSection;
use App\Models\Inspection;
use App\Models\InspectionAnswer;
use App\Models\InspectionSection;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;

/**
 * The inspector's filled-in checklist: copying the checklist when the
 * inspection starts, saving answers, and checking it's complete.
 */
class InspectionService
{
    /**
     * The booking's inspection, once it has started. Bookings started
     * before checklists existed get theirs on first use.
     *
     * @throws InvalidBookingStatusException
     */
    public function forBooking(Booking $booking): Inspection
    {
        if (! in_array($booking->status, [BookingStatus::InProgress, BookingStatus::Completed], true)) {
            throw new InvalidBookingStatusException('Start the inspection first.');
        }

        return $booking->inspection ?? $this->createFor($booking);
    }

    /**
     * Copy the active checklist into a new inspection of this booking.
     * Returns the existing one if it already has one.
     */
    public function createFor(Booking $booking): Inspection
    {
        return DB::transaction(function () use ($booking) {
            $existing = Inspection::where('booking_id', $booking->id)->first();

            if ($existing) {
                return $existing;
            }

            $inspection = new Inspection;
            $inspection->booking_id = $booking->id;
            $inspection->inspector_id = $booking->inspector_id;
            $inspection->started_at = $booking->started_at ?? now();
            $inspection->save();

            $sections = ChecklistSection::active()
                ->ordered()
                ->with(['items' => fn ($query) => $query->active()->with([
                    'answerSet.options' => fn ($query) => $query->active(),
                ])])
                ->get()
                ->filter(fn (ChecklistSection $section) => $section->items->isNotEmpty());

            foreach ($sections->values() as $position => $section) {
                $copy = new InspectionSection;
                $copy->inspection_id = $inspection->id;
                $copy->checklist_section_id = $section->id;
                $copy->name = $section->name;
                $copy->sort_order = $position + 1;
                $copy->save();

                InspectionAnswer::insert($section->items->values()->map(
                    fn (ChecklistItem $item, int $index) => $this->copyItem($item, $copy, $index + 1)
                )->all());
            }

            // Fresh copy, so a GET that created it still answers 200, not 201.
            return $inspection->fresh();
        });
    }

    /**
     * Save a batch of answers. Each entry is already validated against its
     * item's type by SaveAnswersRequest.
     *
     * @param  list<array<string, mixed>>  $answers
     *
     * @throws InvalidBookingStatusException
     */
    public function saveAnswers(Inspection $inspection, array $answers): void
    {
        DB::transaction(function () use ($inspection, $answers) {
            $this->assertOpen($inspection);

            $rows = $inspection->answers()->whereKey(array_column($answers, 'id'))->get()->keyBy('id');

            foreach ($answers as $data) {
                $answer = $rows[$data['id']];

                if ($data['clear'] ?? false) {
                    $this->clear($answer);
                } else {
                    $this->fill($answer, $data);
                }

                $answer->save();
            }
        });
    }

    /**
     * @throws InvalidBookingStatusException
     */
    public function saveComment(InspectionSection $section, ?string $comment): InspectionSection
    {
        return DB::transaction(function () use ($section, $comment) {
            $this->assertOpen($section->inspection);

            $section->comment = $comment;
            $section->save();

            return $section;
        });
    }

    /**
     * Every pick-one, number and percentage item must be answered.
     */
    public function assertComplete(Inspection $inspection): void
    {
        $missing = $inspection->answers()
            ->whereNull('answered_at')
            ->where('answer_type', '!=', AnswerType::Text->value)
            ->join('inspection_sections', 'inspection_sections.id', '=', 'inspection_answers.inspection_section_id')
            ->orderBy('inspection_sections.sort_order')
            ->orderBy('inspection_answers.sort_order')
            ->get(['inspection_answers.id', 'inspection_sections.name as section_name', 'inspection_answers.label']);

        if ($missing->isEmpty()) {
            return;
        }

        $count = $missing->count();

        throw new HttpResponseException(response()->json([
            'message' => 'Answer every item before completing.',
            'errors' => ['answers' => [$count === 1 ? '1 item is not answered.' : "{$count} items are not answered."]],
            'missing' => $missing->map(fn (InspectionAnswer $answer) => [
                'id' => $answer->id,
                'section' => $answer->section_name,
                'label' => $answer->label,
            ])->all(),
        ], 422));
    }

    public function markCompleted(Inspection $inspection): void
    {
        $inspection->completed_at = now();
        $inspection->save();
    }

    /**
     * ok / problem / not applicable / info, from the answer and the copied
     * options and limits. The app never sends this.
     */
    public function outcomeFor(InspectionAnswer $answer): AnswerOutcome
    {
        return match ($answer->answer_type) {
            AnswerType::Choice => AnswerOutcome::from($answer->option($answer->option_id)['outcome']),
            AnswerType::Number, AnswerType::Percentage => $this->isOutsideLimits($answer)
                ? AnswerOutcome::Problem
                : AnswerOutcome::Ok,
            AnswerType::Text => AnswerOutcome::Info,
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function copyItem(ChecklistItem $item, InspectionSection $section, int $position): array
    {
        $options = $item->answer_type === AnswerType::Choice
            ? json_encode($item->answerSet?->options->map(fn (ChecklistAnswerOption $option) => [
                'id' => $option->id,
                'label' => $option->label,
                'outcome' => $option->outcome->value,
            ])->values()->all() ?? [])
            : null;

        return [
            'inspection_id' => $section->inspection_id,
            'inspection_section_id' => $section->id,
            'checklist_item_id' => $item->id,
            'label' => $item->label,
            'description' => $item->description,
            'answer_type' => $item->answer_type->value,
            'options' => $options,
            'unit' => $item->unit,
            'min_value' => $item->min_value,
            'max_value' => $item->max_value,
            'problem_below' => $item->problem_below,
            'problem_above' => $item->problem_above,
            'sort_order' => $position,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function fill(InspectionAnswer $answer, array $data): void
    {
        match ($answer->answer_type) {
            AnswerType::Choice => $answer->forceFill([
                'option_id' => (int) $data['option_id'],
                'option_label' => $answer->option((int) $data['option_id'])['label'],
            ]),
            AnswerType::Number, AnswerType::Percentage => $answer->number_value = (float) $data['value'],
            AnswerType::Text => $answer->text_value = $data['text'],
        };

        if (array_key_exists('note', $data)) {
            $answer->note = $data['note'];
        }

        $answer->outcome = $this->outcomeFor($answer);
        $answer->answered_at = now();
    }

    private function clear(InspectionAnswer $answer): void
    {
        $answer->forceFill([
            'option_id' => null,
            'option_label' => null,
            'number_value' => null,
            'text_value' => null,
            'note' => null,
            'outcome' => null,
            'answered_at' => null,
        ]);
    }

    private function isOutsideLimits(InspectionAnswer $answer): bool
    {
        return ($answer->problem_below !== null && $answer->number_value < $answer->problem_below)
            || ($answer->problem_above !== null && $answer->number_value > $answer->problem_above);
    }

    /**
     * @throws InvalidBookingStatusException
     */
    private function assertOpen(Inspection $inspection): void
    {
        if (Inspection::whereKey($inspection->id)->lockForUpdate()->value('completed_at') !== null) {
            throw new InvalidBookingStatusException('This inspection is already completed.');
        }
    }
}
