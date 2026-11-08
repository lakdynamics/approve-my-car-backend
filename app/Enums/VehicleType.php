<?php

namespace App\Enums;

enum VehicleType: string
{
    case Car = 'car';
    case Van = 'van';
    case Suv = 'suv';
    case DoubleCab = 'double_cab';
    case Motorcycle = 'motorcycle';
    case ThreeWheeler = 'three_wheeler';
    case Lorry = 'lorry';
    case Bus = 'bus';
    case Other = 'other';
}
