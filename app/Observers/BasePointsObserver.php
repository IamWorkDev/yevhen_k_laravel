<?php

namespace App\Observers;

use App\User;

class BasePointsObserver
{
    /**
     * Action create point
     *
     * @param $user_id
     */
    protected function created_point($user_id)
    {
        User::updatePoints($user_id, true);
    }

    /**
     * Action remove point
     *
     * @param $user_id
     */
    protected function deleted_point($user_id)
    {
        User::updatePoints($user_id, false);
    }

    /**
     * Action remove point
     *
     * @param $user_id
     */
    protected function restored_point($user_id)
    {
        User::updatePoints($user_id, false);
    }
}
