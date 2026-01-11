<?php

namespace App\Policies;

use App\Models\Alert;
use App\Models\User;

class AlertPolicy
{
    /**
     * Determine whether the user can view any alerts.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the alert.
     */
    public function view(User $user, Alert $alert): bool
    {
        return $user->id === $alert->user_id;
    }

    /**
     * Determine whether the user can create alerts.
     */
    public function create(User $user): bool
    {
        // Check alert limit (max 50 active alerts per user)
        $activeCount = Alert::where('user_id', $user->id)
            ->where('status', 'active')
            ->count();

        return $activeCount < 50;
    }

    /**
     * Determine whether the user can update the alert.
     */
    public function update(User $user, Alert $alert): bool
    {
        return $user->id === $alert->user_id;
    }

    /**
     * Determine whether the user can delete the alert.
     */
    public function delete(User $user, Alert $alert): bool
    {
        return $user->id === $alert->user_id;
    }

    /**
     * Determine whether the user can restore the alert.
     */
    public function restore(User $user, Alert $alert): bool
    {
        return $user->id === $alert->user_id;
    }

    /**
     * Determine whether the user can permanently delete the alert.
     */
    public function forceDelete(User $user, Alert $alert): bool
    {
        return $user->id === $alert->user_id;
    }

    /**
     * Determine whether the user can snooze the alert.
     */
    public function snooze(User $user, Alert $alert): bool
    {
        return $user->id === $alert->user_id && $alert->isActive();
    }

    /**
     * Determine whether the user can backtest the alert.
     */
    public function backtest(User $user, Alert $alert): bool
    {
        return $user->id === $alert->user_id;
    }

    /**
     * Determine whether the user can duplicate the alert.
     */
    public function duplicate(User $user, Alert $alert): bool
    {
        // Must own the alert and have room for more
        if ($user->id !== $alert->user_id) {
            return false;
        }

        return $this->create($user);
    }
}
