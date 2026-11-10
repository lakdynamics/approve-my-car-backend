<?php

namespace App\Enums;

/**
 * The part of the day the customer wants the inspection.
 */
enum TimeSlot: string
{
    case Morning = 'morning';
    case Afternoon = 'afternoon';
    case Evening = 'evening';

    public function label(): string
    {
        return match ($this) {
            self::Morning => '8:00 AM – 12:00 PM',
            self::Afternoon => '12:00 PM – 4:00 PM',
            self::Evening => '4:00 PM – 7:00 PM',
        };
    }

    /**
     * Position in the day, for sorting.
     */
    public function order(): int
    {
        return match ($this) {
            self::Morning => 1,
            self::Afternoon => 2,
            self::Evening => 3,
        };
    }
}
