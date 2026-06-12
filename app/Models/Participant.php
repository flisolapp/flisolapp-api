<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Participant extends Model
{
    use SoftDeletes;

    const DELETED_AT = 'removed_at';

    protected $fillable = [
        'edition_id',
        'people_id',
        'presented_at',
        'prizedraw_confirmation_at',
        'prizedraw_winner_at',
        'prizedraw_order',
        'prizedraw_description',
        'removed_at',
    ];

    protected $casts = [
        'presented_at' => 'datetime',
        'prizedraw_confirmation_at' => 'datetime',
        'prizedraw_winner_at' => 'datetime',
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
