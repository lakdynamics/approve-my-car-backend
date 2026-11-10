<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Auth\Access\Response;

/**
 * Only the owner can see or change a vehicle. Anyone else gets a 404,
 * so the API doesn't reveal that the vehicle exists.
 */
class VehiclePolicy
{
    public function view(User $user, Vehicle $vehicle): Response
    {
        return $this->ownerOnly($user, $vehicle);
    }

    public function update(User $user, Vehicle $vehicle): Response
    {
        return $this->ownerOnly($user, $vehicle);
    }

    public function delete(User $user, Vehicle $vehicle): Response
    {
        return $this->ownerOnly($user, $vehicle);
    }

    private function ownerOnly(User $user, Vehicle $vehicle): Response
    {
        return $vehicle->user_id === $user->id
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
