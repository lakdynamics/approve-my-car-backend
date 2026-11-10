<?php

namespace App\Services\Profile;

use App\Enums\BookingStatus;
use App\Models\User;
use App\Services\Auth\TokenService;
use App\Services\Bookings\BookingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Password changes and account deletion.
 */
class AccountService
{
    public function __construct(private TokenService $tokens, private BookingService $bookings) {}

    /**
     * Set a new password and sign out every other device. The device
     * that made the change stays signed in.
     */
    public function changePassword(User $user, ?PersonalAccessToken $currentToken, string $newPassword): void
    {
        DB::transaction(function () use ($user, $currentToken, $newPassword) {
            $user->password = $newPassword;
            $user->save();

            if ($currentToken === null) {
                $this->tokens->revokeAllFor($user);
            } else {
                $this->tokens->revokeAllExcept($user, $currentToken);
            }
        });
    }

    /**
     * Close the account.
     *
     * The row is kept (soft deleted) so future records such as inspections
     * still point somewhere, but personal details are wiped and the email
     * is freed, so the person can sign up again with it later.
     */
    public function delete(User $user): void
    {
        DB::transaction(function () use ($user) {
            $this->tokens->revokeAllFor($user);

            // Bookings not yet started are cancelled, so inspectors don't
            // turn up. One already in progress is left for the inspector.
            $user->bookings()
                ->whereIn('status', [BookingStatus::Pending, BookingStatus::Accepted])
                ->lockForUpdate()->get()->each(function ($booking): void {
                    $this->bookings->cancel($booking, 'Account deleted');
                });

            // Vehicles are soft deleted to keep history; addresses are
            // personal data with no history value, so they are removed.
            $user->vehicles()->delete();
            $user->addresses()->delete();
            $user->notifications()->delete();

            $user->forceFill([
                'first_name' => 'Deleted',
                'last_name' => 'User',
                'phone' => null,
                'email' => "deleted-{$user->id}@deleted.invalid",
                'password' => Str::random(64),
                'remember_token' => null,
            ])->save();

            $user->delete();
        });
    }
}
