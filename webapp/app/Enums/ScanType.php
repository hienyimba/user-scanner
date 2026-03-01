<?php

declare(strict_types=1);

namespace App\Enums;

enum ScanType: string
{
    case Username = 'username';
    case Email = 'email';
}
