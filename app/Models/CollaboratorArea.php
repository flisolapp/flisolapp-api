<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CollaboratorArea extends Model
{
    use SoftDeletes;

    const DELETED_AT = 'removed_at';

    protected $fillable = [
        'collaborator_id',
        'collaboration_area_id',
        'removed_at',
    ];

    protected $casts = [
        'removed_at' => 'datetime',
    ];

    public function collaborator()
    {
        return $this->belongsTo(Collaborator::class, 'collaborator_id');
    }

    public function area()
    {
        return $this->belongsTo(CollaborationArea::class, 'collaboration_area_id');
    }
}
