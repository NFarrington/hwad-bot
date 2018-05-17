<?php

namespace App\Models;

/**
 * App\Models\Points
 *
 * @property int $id
 * @property int $guild_id
 * @property string $house
 * @property int $points
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Revision[] $dataChanges
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Points whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Points whereGuildId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Points whereHouse($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Points whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Points wherePoints($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Points whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class Points extends Model
{
    //
}
