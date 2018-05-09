<?php

namespace App\Models;

class Member extends Model
{
    /**
     * The attributes that are trackable.
     *
     * @var array
     */
    protected $tracked = ['username', 'nickname'];
}
