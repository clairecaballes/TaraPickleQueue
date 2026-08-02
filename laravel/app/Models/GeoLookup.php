<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Cached IP -> country/region/city resolution (see GeoIpService). Storing
 * lookups locally means the visitor middleware never depends on the network
 * being available on every request.
 */
class GeoLookup extends Model
{
    use HasFactory;

    protected $fillable = [
        'ip',
        'country',
        'region',
        'city',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'resolved_at' => 'datetime',
        ];
    }
}
