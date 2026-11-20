<?php

namespace App\Http\Resources\V1;

use App\Enums\AnswerOutcome;
use App\Models\Inspection;
use App\Models\InspectionAnswer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The whole filled-in checklist. Load "sections.answers".
 *
 * @mixin Inspection
 */
class InspectionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $answers = $this->sections->flatMap(fn ($section) => $section->answers);

        return [
            'id' => $this->id,
            'booking_id' => $this->booking_id,
            'started_at' => $this->started_at,
            'completed_at' => $this->completed_at,
            'progress' => [
                'answered' => $answers->filter(fn (InspectionAnswer $answer) => $answer->isAnswered())->count(),
                'required' => $answers->filter(fn (InspectionAnswer $answer) => $answer->isRequired())->count(),
                'required_answered' => $answers->filter(
                    fn (InspectionAnswer $answer) => $answer->isRequired() && $answer->isAnswered()
                )->count(),
                'total' => $answers->count(),
                'problems' => $answers->where('outcome', AnswerOutcome::Problem)->count(),
            ],
            'sections' => InspectionSectionResource::collection($this->sections),
        ];
    }
}
