<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VasServiceProvider extends Model
{
    protected $table = 'service_provider';

    protected $primaryKey = 'id';

    protected $fillable = [
        'provider_name',
        'nationnalite',
        'id_fiscale',
        'adresse',
    ];

    public $timestamps = false;

    public function services(): HasMany
    {
        return $this->hasMany(Service::class, 'provider_id', 'id');
    }
}
