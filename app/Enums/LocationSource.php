<?php

namespace App\Enums;

/**
 * How the customer chose the inspection location.
 */
enum LocationSource: string
{
    /** The phone's current location. */
    case Gps = 'gps';

    /** A pin dropped on a map. */
    case Map = 'map';

    /** One of the customer's saved addresses. */
    case SavedAddress = 'saved_address';
}
