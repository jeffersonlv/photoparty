<?php

namespace App\Enums;

enum AccountStatus: string
{
    case Trial = 'trial';
    case TrialEnded = 'trial_ended';
    case Active = 'active';
    case PastDue = 'past_due';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return __('app.account_status.'.$this->value);
    }
}
