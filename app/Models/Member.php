<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * App\Models\Member
 *
 * @property int $id
 * @property string $uid
 * @property int $guild_id
 * @property string $username
 * @property string|null $nickname
 * @property string|null $last_message_at
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 * @property string|null $deleted_at
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Revision[] $dataChanges
 * @method static bool|null forceDelete()
 * @method static \Illuminate\Database\Query\Builder|\App\Models\Member onlyTrashed()
 * @method static bool|null restore()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Member whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Member whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Member whereGuildId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Member whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Member whereLastMessageAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Member whereNickname($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Member whereUid($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Member whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Member whereUsername($value)
 * @method static \Illuminate\Database\Query\Builder|\App\Models\Member withTrashed()
 * @method static \Illuminate\Database\Query\Builder|\App\Models\Member withoutTrashed()
 * @mixin \Eloquent
 */
class Member extends Model
{
    use SoftDeletes;

    /**
     * The attributes that are trackable.
     *
     * @var array
     */
    protected $tracked = ['username', 'nickname'];
}
