<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Throwable;

class Edition extends Model
{
    use SoftDeletes;

    const DELETED_AT = 'removed_at';

    protected $fillable = [
        'year',
        'options',
        'active',
        'removed_at',
    ];

    protected $casts = [
        'active' => 'boolean',
        'removed_at' => 'datetime',
    ];

    public function places()
    {
        return $this->hasMany(EditionPlace::class, 'edition_id');
    }

    public function talks()
    {
        return $this->hasMany(Talk::class, 'edition_id');
    }

    public function participants()
    {
        return $this->hasMany(Participant::class, 'edition_id');
    }

    public function collaborators()
    {
        return $this->hasMany(Collaborator::class, 'edition_id');
    }

    public function organizers()
    {
        return $this->hasMany(Organizer::class, 'edition_id');
    }

    /**
     * Decode the JSON options column into a plain object.
     * Returns null on any decode error to avoid cascading failures.
     */
    public function getOptionsAttribute($value): ?object
    {
        try {
            $decoded = json_decode($value);

            return json_last_error() === JSON_ERROR_NONE ? $decoded : null;
        } catch (Throwable) {
            return null;
        }
    }
}
