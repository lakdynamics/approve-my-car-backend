<?php

namespace App\Policies;

use App\Models\User;
use App\Models\UserAddress;
use Illuminate\Auth\Access\Response;

/**
 * Only the owner can see or change an address. Anyone else gets a 404,
 * so the API doesn't reveal that the address exists.
 */
class UserAddressPolicy
{
    public function view(User $user, UserAddress $address): Response
    {
        return $this->ownerOnly($user, $address);
    }

    public function update(User $user, UserAddress $address): Response
    {
        return $this->ownerOnly($user, $address);
    }

    public function delete(User $user, UserAddress $address): Response
    {
        return $this->ownerOnly($user, $address);
    }

    private function ownerOnly(User $user, UserAddress $address): Response
    {
        return $address->user_id === $user->id
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
