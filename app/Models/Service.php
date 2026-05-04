<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
    protected $table = 'services';

    protected $primaryKey = 'id';

    protected $fillable = [
        'service_name',
        'short_code',
        'keyword',
        'type',
        'price',
        'provider_id',
    ];

    public $timestamps = false;

    public function provider(): BelongsTo
    {
        return $this->belongsTo(VasServiceProvider::class, 'provider_id', 'id');
    }
}
