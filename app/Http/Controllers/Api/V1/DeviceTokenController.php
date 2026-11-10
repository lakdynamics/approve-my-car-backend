<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Notifications\StoreDeviceTokenRequest;
use App\Models\DeviceToken;
use App\Models\RefreshToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;

class DeviceTokenController extends Controller
{
    public function store(StoreDeviceTokenRequest $request): JsonResponse
    {
        $data = $request->validated();
        $hash = hash('sha256', $data['token']);
        $accessToken = $request->user()->currentAccessToken();
        $familyId = $accessToken instanceof PersonalAccessToken
            ? RefreshToken::query()->where('access_token_id', $accessToken->getKey())
                ->whereNull('revoked_at')->value('family_id')
            : null;

        $device = DB::transaction(function () use ($request, $data, $hash, $familyId): DeviceToken {
            DeviceToken::query()->where('token_hash', $hash)
                ->where('device_id', '!=', $data['device_id'])->delete();

            return DeviceToken::query()->updateOrCreate(['device_id' => $data['device_id']], [
                'user_id' => $request->user()->id,
                'family_id' => $familyId,
                'platform' => $data['platform'],
                'token' => $data['token'],
                'token_hash' => $hash,
                'last_seen_at' => now(),
            ]);
        });

        return response()->json(['data' => [
            'device_id' => $device->device_id,
            'platform' => $device->platform,
            'last_seen_at' => $device->last_seen_at,
        ]], $device->wasRecentlyCreated ? 201 : 200);
    }

    public function destroy(Request $request, string $device): Response
    {
        $request->user()->deviceTokens()->where('device_id', $device)->firstOrFail()->delete();

        return response()->noContent();
    }
}
