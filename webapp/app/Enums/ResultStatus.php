<?php

declare(strict_types=1);

namespace App\Enums;

enum ResultStatus: string
{
    case Taken = 'taken';
    case Available = 'available';
    case Registered = 'registered';
    case NotRegistered = 'not_registered';
    case Error = 'error';
}
