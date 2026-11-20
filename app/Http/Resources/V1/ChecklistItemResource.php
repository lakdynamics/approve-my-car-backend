<?php

namespace App\Http\Resources\V1;

use App\Models\ChecklistItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ChecklistItem
 */
class ChecklistItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'checklist_section_id' => $this->checklist_section_id,
            'label' => $this->label,
            'description' => $this->description,
            'answer_type' => $this->answer_type,
            'answer_set' => $this->whenLoaded('answerSet', fn () => $this->answerSet
                ? new ChecklistAnswerSetResource($this->answerSet)
                : null),
            'unit' => $this->unit,
            'min_value' => $this->min_value,
            'max_value' => $this->max_value,
            'problem_below' => $this->problem_below,
            'problem_above' => $this->problem_above,
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
        ];
    }
}
