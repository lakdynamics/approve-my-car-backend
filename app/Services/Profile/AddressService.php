<?php

namespace App\Services\Profile;

use App\Models\User;
use App\Models\UserAddress;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Saves addresses and keeps exactly one default address per user
 * (while they have any).
 */
class AddressService
{
    public const MAX_ADDRESSES = 10;

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException When the user already has the maximum number of addresses.
     */
    public function create(User $user, array $data, bool $makeDefault = false): UserAddress
    {
        return DB::transaction(function () use ($user, $data, $makeDefault) {
            // Lock the user row so two requests at once can't both pass the
            // limit check or both become the default.
            User::query()->whereKey($user->id)->lockForUpdate()->first();

            $count = $user->addresses()->count();

            if ($count >= self::MAX_ADDRESSES) {
                throw ValidationException::withMessages([
                    'address' => ['You can save up to '.self::MAX_ADDRESSES.' addresses.'],
                ]);
            }

            $address = new UserAddress($data);
            $address->user()->associate($user);
            // The first address is always the default.
            $address->is_default = $makeDefault || $count === 0;

            if ($address->is_default) {
                $this->clearDefault($user);
            }

            $address->save();

            return $address;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  bool|null  $makeDefault  true makes it the default. false and null leave the default as it is,
     *                                  so there is always a default.
     */
    public function update(UserAddress $address, array $data, ?bool $makeDefault = null): UserAddress
    {
        return DB::transaction(function () use ($address, $data, $makeDefault) {
            $address->fill($data);

            if ($makeDefault === true && ! $address->is_default) {
                $this->clearDefault($address->user);
                $address->is_default = true;
            }

            $address->save();

            return $address;
        });
    }

    /**
     * Delete an address. If it was the default, the newest remaining
     * address becomes the default.
     */
    public function delete(UserAddress $address): void
    {
        DB::transaction(function () use ($address) {
            $address->delete();

            if (! $address->is_default) {
                return;
            }

            $address->user->addresses()
                ->latest()
                ->latest('id')
                ->first()
                ?->forceFill(['is_default' => true])
                ->save();
        });
    }

    private function clearDefault(User $user): void
    {
        $user->addresses()->where('is_default', true)->update(['is_default' => false]);
    }
}
