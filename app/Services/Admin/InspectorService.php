<?php

namespace App\Services\Admin;

use App\Enums\BookingStatus;
use App\Enums\UserType;
use App\Exceptions\Admin\InvalidUserTypeException;
use App\Models\User;
use App\Services\Auth\TokenService;
use Illuminate\Support\Facades\DB;

/**
 * Admin actions that create inspectors or move users between
 * "user" and "inspector".
 *
 * Changing someone's type signs them out everywhere, so the app picks
 * up the new role on their next login.
 */
class InspectorService
{
    public function __construct(private TokenService $tokens) {}

    /**
     * Create a ready-to-use inspector account with a password the admin chose.
     *
     * @param  array{first_name: string, last_name: string, email: string, password: string}  $data
     */
    public function create(array $data): User
    {
        $user = new User([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'password' => $data['password'],
        ]);

        $user->type = UserType::Inspector;
        // An admin created this account, so the email doesn't need confirming.
        $user->email_verified_at = now();
        $user->save();

        return $user;
    }

    /**
     * @throws InvalidUserTypeException
     */
    public function promote(User $user): User
    {
        if ($user->isInspector()) {
            return $user;
        }

        if (! $user->isUser()) {
            throw new InvalidUserTypeException('Only normal users can be made inspectors.');
        }

        return $this->changeType($user, UserType::Inspector);
    }

    /**
     * @throws InvalidUserTypeException
     */
    public function demote(User $user): User
    {
        if (! $user->isInspector()) {
            throw new InvalidUserTypeException('This user is not an inspector.');
        }

        return DB::transaction(function () use ($user) {
            // Give their unfinished bookings back to the pool, so another
            // inspector can pick them up.
            $user->assignedBookings()
                ->whereIn('status', [BookingStatus::Accepted, BookingStatus::InProgress])
                ->update([
                    'status' => BookingStatus::Pending->value,
                    'inspector_id' => null,
                    'accepted_at' => null,
                    'started_at' => null,
                    'updated_at' => now(),
                ]);

            return $this->changeType($user, UserType::User);
        });
    }

    private function changeType(User $user, UserType $type): User
    {
        DB::transaction(function () use ($user, $type) {
            $user->type = $type;
            $user->save();

            $this->tokens->revokeAllFor($user);
        });

        return $user;
    }
}
