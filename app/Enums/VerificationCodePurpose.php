<?php

namespace App\Enums;

/**
 * Why a verification code was emailed. Each purpose has its own codes,
 * so a code sent for one purpose can never be used for another.
 */
enum VerificationCodePurpose: string
{
    case Register = 'register';
}
