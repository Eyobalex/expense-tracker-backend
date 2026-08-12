<?php

namespace App\Domain\Sync\Enums;

enum SyncStatus: string
{
    case Queued = 'queued';
    case Processing = 'processing';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Conflict = 'conflict';
}
