<?php

namespace App\Enums;

/**
 * What kind of account a user has. Stored as a number in users.type.
 */
enum UserType: int
{
    case Admin = 1;

    /** Staff the company sends out to inspect vehicles. */
    case Inspector = 2;

    case User = 3;
}
