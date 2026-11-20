<?php

namespace App\Http\Resources\V1;

use App\Enums\AnswerOutcome;
use App\Models\InspectionAnswer;
use App\Models\InspectionSection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A section of the inspection with its comment, progress and items.
 * Load "answers" for the counts and items.
 *
 * @mixin InspectionSection
 */
class InspectionSectionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'comment' => $this->comment,
            'answered' => $this->whenLoaded('answers', fn () => $this->answers->filter(
                fn (InspectionAnswer $answer) => $answer->isAnswered()
            )->count()),
            'total' => $this->whenLoaded('answers', fn () => $this->answers->count()),
            'problems' => $this->whenLoaded('answers', fn () => $this->answers->where('outcome', AnswerOutcome::Problem)->count()),
            'items' => InspectionAnswerResource::collection($this->whenLoaded('answers')),
        ];
    }
}
