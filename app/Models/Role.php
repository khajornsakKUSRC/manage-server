<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A descriptive label for what a user manages (Network, Server, IP Phone,
 * Developer, Computer, …). Roles do NOT grant page access — that stays with
 * users.permissions; they exist so the team can see, at a glance on Manage
 * Users, who looks after what. A user with no roles is a "general user".
 *
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property string $color
 */
#[Fillable(['name', 'description', 'color'])]
class Role extends Model
{
    /**
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }
}
