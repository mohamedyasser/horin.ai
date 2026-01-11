<?php

namespace App\Policies;

use App\Models\AlertHistory;
use App\Models\User;

class AlertHistoryPolicy
{
    /**
     * Determine whether the user can view any alert history.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the alert history.
     */
    public function view(User $user, AlertHistory $history): bool
    {
        return $user->id === $history->user_id;
    }

    /**
     * Determine whether the user can acknowledge the alert history.
     */
    public function acknowledge(User $user, AlertHistory $history): bool
    {
        return $user->id === $history->user_id && ! $history->isAcknowledged();
    }
}
