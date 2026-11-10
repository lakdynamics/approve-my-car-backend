<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Booking calendar
    |--------------------------------------------------------------------------
    |
    | The app stores times in UTC, but "today" and "tomorrow" for bookings
    | mean the customer's local day, so dates are worked out in this zone.
    |
    */

    'timezone' => env('BOOKINGS_TIMEZONE', 'Asia/Colombo'),

    // A booking can be made from this many days ahead...
    'min_days_ahead' => 1,

    // ...up to this many days ahead.
    'max_days_ahead' => 30,

    // Most bookings a user can create per hour.
    'max_created_per_hour' => 10,

    /*
    |--------------------------------------------------------------------------
    | Service area
    |--------------------------------------------------------------------------
    |
    | A rough box around Sri Lanka. Coordinates outside it are rejected,
    | which also catches latitude and longitude sent the wrong way round.
    |
    */

    'service_area' => [
        'latitude' => [5.8, 10.0],
        'longitude' => [79.4, 82.0],
    ],

];
