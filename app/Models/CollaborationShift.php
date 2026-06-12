<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CollaborationShift extends Model
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

    public function collaboratorAvailabilities()
    {
        return $this->hasMany(CollaboratorAvailability::class, 'collaborator_shift_id');
    }
}
