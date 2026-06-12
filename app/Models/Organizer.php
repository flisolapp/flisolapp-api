<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Organizer extends Model
{
    use SoftDeletes;

    const DELETED_AT = 'removed_at';

    protected $fillable = [
        'edition_id',
        'people_id',
        'presented_at',
        'removed_at',
    ];

    protected $casts = [
        'presented_at' => 'datetime',
        'removed_at' => 'datetime',
    ];

    public function person()
    {
        return $this->belongsTo(People::class, 'people_id');
    }

    public function edition()
    {
        return $this->belongsTo(Edition::class, 'edition_id');
    }
}
