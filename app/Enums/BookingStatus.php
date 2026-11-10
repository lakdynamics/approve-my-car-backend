<?php

namespace App\Enums;

/**
 * pending → accepted → in_progress → completed, or cancelled by the
 * customer before the inspection starts.
 */
enum BookingStatus: string
{
    /** Waiting for an inspector to accept it. */
    case Pending = 'pending';

    /** An inspector has claimed it. */
    case Accepted = 'accepted';

    /** The inspector has started the inspection. */
    case InProgress = 'in_progress';

    case Completed = 'completed';

    case Cancelled = 'cancelled';

    /**
     * Statuses that still need work. A vehicle can only have one booking
     * in one of these at a time.
     *
     * @return list<self>
     */
    public static function active(): array
    {
        return [self::Pending, self::Accepted, self::InProgress];
    }

    public function isCancellableByUser(): bool
    {
        return in_array($this, [self::Pending, self::Accepted], true);
    }
}
