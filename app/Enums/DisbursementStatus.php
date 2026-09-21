<?php

namespace App\Enums;

enum DisbursementStatus: string
{
    case SCHEDULED = 'scheduled';
    case PENDING = 'pending';
    case PROCESSING = 'processing';
    case SUCCESS = 'success';
    case FAILED = 'failed';

    public function isFinal(): bool
    {
        return in_array($this, [self::SUCCESS, self::FAILED]);
    }
}