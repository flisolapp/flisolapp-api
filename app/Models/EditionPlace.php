<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class EditionPlace extends Model
{
    use SoftDeletes;

    const DELETED_AT = 'removed_at';

    protected $fillable = [
        '_id',
        'edition_id',
        'kind',
        'name',
        'description',
        'floor',
        'capacity',
        'active',
        'removed_at',
    ];

    protected $casts = [
        'capacity' => 'integer',
        'active' => 'boolean',
        'removed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (EditionPlace $place) {
            if (empty($place->_id)) {
                $place->_id = (string)Str::uuid7();
            }
        });
    }

    public function edition()
    {
        return $this->belongsTo(Edition::class, 'edition_id');
    }
}
