<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CollaboratorAvailability extends Model
{
    use SoftDeletes;

    const DELETED_AT = 'removed_at';

    protected $fillable = [
        'collaborator_id',
        'collaborator_shift_id',
        'removed_at',
    ];

    protected $casts = [
        'removed_at' => 'datetime',
    ];

    public function collaborator()
    {
        return $this->belongsTo(Collaborator::class, 'collaborator_id');
    }

    public function shift()
    {
        return $this->belongsTo(CollaborationShift::class, 'collaborator_shift_id');
    }
}
