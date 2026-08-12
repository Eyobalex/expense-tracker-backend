<?php

namespace App\Domain\Shared\Enums;

enum JobState: string
{
    case Queued = 'queued';
    case Processing = 'processing';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
}
