<?php

namespace App\Enums;

/**
 * The 25 districts of Sri Lanka.
 */
enum District: string
{
    // Western
    case Colombo = 'colombo';
    case Gampaha = 'gampaha';
    case Kalutara = 'kalutara';
    // Central
    case Kandy = 'kandy';
    case Matale = 'matale';
    case NuwaraEliya = 'nuwara_eliya';
    // Southern
    case Galle = 'galle';
    case Matara = 'matara';
    case Hambantota = 'hambantota';
    // Northern
    case Jaffna = 'jaffna';
    case Kilinochchi = 'kilinochchi';
    case Mannar = 'mannar';
    case Vavuniya = 'vavuniya';
    case Mullaitivu = 'mullaitivu';
    // Eastern
    case Batticaloa = 'batticaloa';
    case Ampara = 'ampara';
    case Trincomalee = 'trincomalee';
    // North Western
    case Kurunegala = 'kurunegala';
    case Puttalam = 'puttalam';
    // North Central
    case Anuradhapura = 'anuradhapura';
    case Polonnaruwa = 'polonnaruwa';
    // Uva
    case Badulla = 'badulla';
    case Monaragala = 'monaragala';
    // Sabaragamuwa
    case Ratnapura = 'ratnapura';
    case Kegalle = 'kegalle';

    /**
     * Display name, e.g. "Nuwara Eliya".
     */
    public function label(): string
    {
        return ucwords(str_replace('_', ' ', $this->value));
    }
}
