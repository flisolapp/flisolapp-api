<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TalkSubject extends Model
{
    use SoftDeletes;

    const DELETED_AT = 'removed_at';

    protected $fillable = [
        'name',
        'removed_at',
    ];

    public function talks()
    {
        return $this->hasMany(Talk::class, 'talk_subject_id');
    }
}
