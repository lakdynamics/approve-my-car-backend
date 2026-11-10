<?php

namespace App\Enums;

enum FuelType: string
{
    case Petrol = 'petrol';
    case Diesel = 'diesel';
    case Hybrid = 'hybrid';
    case PlugInHybrid = 'plug_in_hybrid';
    case Electric = 'electric';
    case Other = 'other';
}
