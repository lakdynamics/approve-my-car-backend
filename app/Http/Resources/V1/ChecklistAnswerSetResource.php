<?php

namespace App\Http\Resources\V1;

use App\Models\ChecklistAnswerSet;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ChecklistAnswerSet
 */
class ChecklistAnswerSetResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'is_active' => $this->is_active,
            'options' => ChecklistAnswerOptionResource::collection($this->whenLoaded('options')),
            'items_count' => $this->whenCounted('items'),
        ];
    }
}
