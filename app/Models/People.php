<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class People extends Model
{
    use SoftDeletes;

    const DELETED_AT = 'removed_at';

    protected $table = 'people';

    protected $fillable = [
        'name',
        'federal_code',
        'email',
        'phone',
        'photo',
        'bio',
        'site',
        'use_free',
        'distro_id',
        'student_info_id',
        'student_place',
        'student_course',
        'address_state',
        'removed_at',
    ];

    protected $casts = [
        'use_free' => 'boolean',
        'removed_at' => 'datetime',
    ];

    public function speakerTalks()
    {
        return $this->hasMany(SpeakerTalk::class, 'speaker_id');
    }

    public function talks()
    {
        return $this->belongsToMany(Talk::class, 'speaker_talks', 'speaker_id', 'talk_id')
            ->whereNull('speaker_talks.removed_at');
    }
}
