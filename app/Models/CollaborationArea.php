<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CollaborationArea extends Model
{
    use SoftDeletes;

    const DELETED_AT = 'removed_at';

    protected $fillable = [
        'name',
        'removed_at',
    ];

    protected $casts = [
        'removed_at' => 'datetime',
    ];

    public function collaboratorAreas()
    {
        return $this->hasMany(CollaboratorArea::class, 'collaboration_area_id');
    }
}
