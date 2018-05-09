<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * App\Models\Guild
 *
 * @property int $id
 * @property string $guild_id
 * @property string $name
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 * @property string|null $deleted_at
 * @method static bool|null forceDelete()
 * @method static \Illuminate\Database\Query\Builder|\App\Models\Guild onlyTrashed()
 * @method static bool|null restore()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Guild whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Guild whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Guild whereGuildId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Guild whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Guild whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Guild whereUpdatedAt($value)
 * @method static \Illuminate\Database\Query\Builder|\App\Models\Guild withTrashed()
 * @method static \Illuminate\Database\Query\Builder|\App\Models\Guild withoutTrashed()
 * @mixin \Eloquent
 */
class Guild extends Model
{
    use SoftDeletes;
}
