<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\UserType;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Admin\ListUsersRequest;
use App\Http\Resources\V1\UserResource;
use App\Models\User;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UserController extends Controller
{
    /**
     * GET /api/v1/admin/users?search=&type=
     */
    public function index(ListUsersRequest $request): AnonymousResourceCollection
    {
        $type = $request->validated('type');

        $users = User::query()
            ->search($request->validated('search'))
            ->when($type !== null, fn ($query) => $query->where('type', UserType::from((int) $type)))
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->paginate(20)
            ->withQueryString();

        return UserResource::collection($users);
    }
}
