<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Talk extends Model
{
    use SoftDeletes;

    const DELETED_AT = 'removed_at';

    protected $fillable = [
        'edition_id',
        'title',
        'description',
        'shift',
        'kind',
        'talk_subject_id',
        'slide_file',
        'slide_url',
        'internal_note',
        'audited_at',
        'audit_note',
        'approved_at',
        'presented_at',
        'removed_at',
    ];

    protected $casts = [
        'audited_at' => 'datetime',
        'approved_at' => 'datetime',
        'presented_at' => 'datetime',
        'removed_at' => 'datetime',
    ];

    public function edition()
    {
        return $this->belongsTo(Edition::class);
    }

    public function talkSubject()
    {
        return $this->belongsTo(TalkSubject::class);
    }

    public function speakerTalks()
    {
        return $this->hasMany(SpeakerTalk::class, 'talk_id');
    }

    public function speakers()
    {
        return $this->belongsToMany(People::class, 'speaker_talks', 'talk_id', 'speaker_id')
            ->whereNull('speaker_talks.removed_at');
    }
}
