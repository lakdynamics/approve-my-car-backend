<?php

namespace App\Http\Resources\V1;

use App\Models\ChecklistAnswerOption;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ChecklistAnswerOption
 */
class ChecklistAnswerOptionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'checklist_answer_set_id' => $this->checklist_answer_set_id,
            'label' => $this->label,
            'outcome' => $this->outcome,
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
        ];
    }
}
