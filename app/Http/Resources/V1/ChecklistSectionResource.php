<?php

namespace App\Http\Resources\V1;

use App\Models\ChecklistSection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ChecklistSection
 */
class ChecklistSectionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
            'items' => ChecklistItemResource::collection($this->whenLoaded('items')),
        ];
    }
}
