<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Least-privilege roles prepared for future assistants or delegated users.
 * The first release only uses Owner, but the model is multi-user ready.
 */
enum MembershipRole: string
{
    case Owner = 'owner';
    case Assistant = 'assistant';
    case Viewer = 'viewer';
    case FinanceReviewer = 'finance_reviewer';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Owner',
            self::Assistant => 'Assistant',
            self::Viewer => 'Viewer',
            self::FinanceReviewer => 'Finance reviewer',
        };
    }
}
