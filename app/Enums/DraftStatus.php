<?php

declare(strict_types=1);

namespace App\Enums;

enum DraftStatus: string
{
    case Draft = 'draft';
    case Approved = 'approved';
    case Sent = 'sent';
    case Rejected = 'rejected';
}
