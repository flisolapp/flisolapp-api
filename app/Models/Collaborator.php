<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Collaborator extends Model
{
    use SoftDeletes;

    const DELETED_AT = 'removed_at';

    protected $fillable = [
        'edition_id',
        'people_id',
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

    public function person()
    {
        return $this->belongsTo(People::class, 'people_id');
    }

    public function edition()
    {
        return $this->belongsTo(Edition::class, 'edition_id');
    }

    public function areas()
    {
        return $this->hasMany(CollaboratorArea::class, 'collaborator_id');
    }

    public function collaborationAreas()
    {
        return $this->belongsToMany(CollaborationArea::class, 'collaborator_areas', 'collaborator_id', 'collaboration_area_id')
            ->whereNull('collaborator_areas.removed_at');
    }

    public function availabilities()
    {
        return $this->hasMany(CollaboratorAvailability::class, 'collaborator_id');
    }

    public function collaborationShifts()
    {
        return $this->belongsToMany(CollaborationShift::class, 'collaborator_availabilities', 'collaborator_id', 'collaborator_shift_id')
            ->whereNull('collaborator_availabilities.removed_at');
    }
}
