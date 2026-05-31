<?php

namespace App\Enums;

enum WorkType: string
{
    case ZirconCrown = 'تركيبة زيركون';
    case PorcelainCrown = 'تركيبة خزف';
    case Crown = 'تلبيسة';
    case Bridge = 'جسر';
    case Denture = 'طقم أسنان';
    case Veneer = 'فينير';
    case Implant = 'زرعة';
    case Braces = 'تقويم';
}
