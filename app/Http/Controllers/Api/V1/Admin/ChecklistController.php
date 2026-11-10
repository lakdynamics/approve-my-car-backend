<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Admin\Checklist\ReorderRequest;
use App\Http\Requests\V1\Admin\Checklist\StoreItemRequest;
use App\Http\Requests\V1\Admin\Checklist\StoreSectionRequest;
use App\Http\Requests\V1\Admin\Checklist\UpdateItemRequest;
use App\Http\Requests\V1\Admin\Checklist\UpdateSectionRequest;
use App\Http\Resources\V1\ChecklistItemResource;
use App\Http\Resources\V1\ChecklistSectionResource;
use App\Models\ChecklistItem;
use App\Models\ChecklistSection;
use App\Services\Checklist\ChecklistService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Admins build the inspection checklist. There is no delete: hiding a
 * section or item (is_active = false) keeps old reports intact.
 */
class ChecklistController extends Controller
{
    public function __construct(private ChecklistService $checklist) {}

    /**
     * GET /api/v1/admin/checklist?include_inactive=1
     *
     * The whole checklist in order: sections, each with its items.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $all = $request->boolean('include_inactive');

        $sections = ChecklistSection::query()
            ->unless($all, fn ($query) => $query->active())
            ->with(['items' => fn ($query) => $query->unless($all, fn ($query) => $query->active())])
            ->ordered()
            ->get();

        return ChecklistSectionResource::collection($sections);
    }

    /**
     * POST /api/v1/admin/checklist/sections
     */
    public function storeSection(StoreSectionRequest $request): JsonResponse
    {
        $section = $this->checklist->createSection($request->validated());

        return (new ChecklistSectionResource($section->load('items')))->response()->setStatusCode(201);
    }

    /**
     * PATCH /api/v1/admin/checklist/sections/{section}
     */
    public function updateSection(UpdateSectionRequest $request, ChecklistSection $section): ChecklistSectionResource
    {
        $section->update($request->validated());

        return new ChecklistSectionResource($section->load('items'));
    }

    /**
     * PUT /api/v1/admin/checklist/sections/order
     */
    public function reorderSections(ReorderRequest $request): AnonymousResourceCollection
    {
        $this->checklist->reorderSections($request->validated('ids'));

        return ChecklistSectionResource::collection(ChecklistSection::query()->ordered()->get());
    }

    /**
     * POST /api/v1/admin/checklist/sections/{section}/items
     */
    public function storeItem(StoreItemRequest $request, ChecklistSection $section): JsonResponse
    {
        $item = $this->checklist->createItem($section, $request->validated());

        return (new ChecklistItemResource($item))->response()->setStatusCode(201);
    }

    /**
     * PATCH /api/v1/admin/checklist/items/{item}
     */
    public function updateItem(UpdateItemRequest $request, ChecklistItem $item): ChecklistItemResource
    {
        return new ChecklistItemResource($this->checklist->updateItem($item, $request->validated()));
    }

    /**
     * PUT /api/v1/admin/checklist/sections/{section}/items/order
     */
    public function reorderItems(ReorderRequest $request, ChecklistSection $section): ChecklistSectionResource
    {
        $this->checklist->reorderItems($section, $request->validated('ids'));

        return new ChecklistSectionResource($section->load('items'));
    }
}
