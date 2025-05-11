<?php

namespace App\Enum;

enum Status: string
{
    case PENDING = 'pending';
    case IN_PROGRESS = 'in_progress';
    case DONE = 'done';
    case ERROR = 'error';
    case FAILED = 'failed';

    public function label(): string
    {
        return match($this) {
            self::PENDING => 'Pending',
            self::IN_PROGRESS => 'In Progress',
            self::DONE => 'Done',
            self::ERROR => 'Error',
            self::FAILED => 'Failed'
        };
    }
}