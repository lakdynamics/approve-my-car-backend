<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Admin\Checklist\ReorderRequest;
use App\Http\Requests\V1\Admin\Checklist\StoreAnswerOptionRequest;
use App\Http\Requests\V1\Admin\Checklist\StoreAnswerSetRequest;
use App\Http\Requests\V1\Admin\Checklist\UpdateAnswerOptionRequest;
use App\Http\Requests\V1\Admin\Checklist\UpdateAnswerSetRequest;
use App\Http\Resources\V1\ChecklistAnswerOptionResource;
use App\Http\Resources\V1\ChecklistAnswerSetResource;
use App\Models\ChecklistAnswerOption;
use App\Models\ChecklistAnswerSet;
use App\Services\Checklist\ChecklistService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Admins manage the answer lists used by "pick one" checklist items,
 * e.g. "Condition": Good / Bad / None. Nothing is deleted, only hidden.
 */
class ChecklistAnswerSetController extends Controller
{
    public function __construct(private ChecklistService $checklist) {}

    /**
     * GET /api/v1/admin/checklist/answer-sets?include_inactive=1
     *
     * Lists A–Z, each with its options in order and how many items use it.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $all = $request->boolean('include_inactive');

        $sets = ChecklistAnswerSet::query()
            ->unless($all, fn ($query) => $query->active())
            ->with(['options' => fn ($query) => $query->unless($all, fn ($query) => $query->active())])
            ->withCount('items')
            ->ordered()
            ->get();

        return ChecklistAnswerSetResource::collection($sets);
    }

    /**
     * POST /api/v1/admin/checklist/answer-sets
     */
    public function store(StoreAnswerSetRequest $request): JsonResponse
    {
        $set = $this->checklist->createAnswerSet($request->validated());

        return (new ChecklistAnswerSetResource($set->load('options')->loadCount('items')))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * PATCH /api/v1/admin/checklist/answer-sets/{answerSet}
     */
    public function update(UpdateAnswerSetRequest $request, ChecklistAnswerSet $answerSet): ChecklistAnswerSetResource
    {
        $set = $this->checklist->updateAnswerSet($answerSet, $request->validated());

        return new ChecklistAnswerSetResource($set->load('options')->loadCount('items'));
    }

    /**
     * POST /api/v1/admin/checklist/answer-sets/{answerSet}/options
     */
    public function storeOption(StoreAnswerOptionRequest $request, ChecklistAnswerSet $answerSet): JsonResponse
    {
        $option = $this->checklist->createAnswerOption($answerSet, $request->validated());

        return (new ChecklistAnswerOptionResource($option))->response()->setStatusCode(201);
    }

    /**
     * PATCH /api/v1/admin/checklist/answer-options/{answerOption}
     */
    public function updateOption(UpdateAnswerOptionRequest $request, ChecklistAnswerOption $answerOption): ChecklistAnswerOptionResource
    {
        return new ChecklistAnswerOptionResource($this->checklist->updateAnswerOption($answerOption, $request->validated()));
    }

    /**
     * PUT /api/v1/admin/checklist/answer-sets/{answerSet}/options/order
     */
    public function reorderOptions(ReorderRequest $request, ChecklistAnswerSet $answerSet): ChecklistAnswerSetResource
    {
        $this->checklist->reorderAnswerOptions($answerSet, $request->validated('ids'));

        return new ChecklistAnswerSetResource($answerSet->load('options')->loadCount('items'));
    }
}
