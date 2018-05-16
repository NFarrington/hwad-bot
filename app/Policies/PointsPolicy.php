<?php

namespace App\Policies;

use CharlotteDunois\Yasmin\Models\Permissions;
use CharlotteDunois\Yasmin\Models\Role;
use Illuminate\Auth\Access\HandlesAuthorization;

class PointsPolicy
{
    use HandlesAuthorization;

    /**
     * Create a new policy instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Determine if a guild member can modify points.
     *
     * @param \CharlotteDunois\Yasmin\Models\GuildMember $member
     * @return bool
     */
    public function modify($member)
    {
        $validRoles = $member->roles->filter(function (Role $role) {
            return $role->permissions->has(Permissions::PERMISSIONS['ADMINISTRATOR'])
                || in_array($role->name, ['Professors', 'Prefects']);
        });

        if ($validRoles->count() === 0) {
            return false;
        }

        return true;
    }
}
