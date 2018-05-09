<?php

namespace App\Models;

/**
 * App\Models\Model
 *
 * @method static int count(string $columns = '*')
 * @method static $this find($id, $columns = ['*'])
 * @method static $this inRandomOrder(string $seed = '')
 * @method static $this where($column, $operator = null, $value = null, $boolean = 'and')
 * @method static $this whereNotIn($column, $values, $boolean = 'and')
 * @method static $this orderBy($column, $direction = 'asc')
 * @mixin \Eloquent
 */
abstract class Model extends \Illuminate\Database\Eloquent\Model
{
    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = [];
}
