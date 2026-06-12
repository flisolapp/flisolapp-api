<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class State extends Model
{
    use SoftDeletes;

    const DELETED_AT = 'removed_at';

    protected $fillable = [
        'name',
        'acronym',
        'removed_at',
    ];

    protected $casts = [
        'removed_at' => 'datetime',
    ];
}
