<?php

namespace App\Policies;

use App\Models\DbCopy;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class DbCopyPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, DbCopy $dbCopy): bool
    {
        return $dbCopy->created_by_user_id === $user->id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, DbCopy $dbCopy): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, DbCopy $dbCopy): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, DbCopy $dbCopy): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, DbCopy $dbCopy): bool
    {
        return false;
    }
}
