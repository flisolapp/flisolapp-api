<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SpeakerTalk extends Model
{
    use SoftDeletes;

    const DELETED_AT = 'removed_at';

    protected $fillable = [
        'speaker_id',
        'talk_id',
        'removed_at',
    ];

    protected $casts = [
        'removed_at' => 'datetime',
    ];

    public function speaker()
    {
        return $this->belongsTo(People::class, 'speaker_id');
    }

    public function talk()
    {
        return $this->belongsTo(Talk::class, 'talk_id');
    }
}
