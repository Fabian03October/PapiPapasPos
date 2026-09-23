<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SaleIncident extends Model
{
    protected $fillable = [
        'sale_id',
        'type',
        'reason',
        'status',
        'requested_by',
        'authorized_by',
        'authorization_method',
        'authorized_at',
    ];

    protected function casts(): array
    {
        return [
            'authorized_at' => 'datetime',
        ];
    }

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    public function requestedBy()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function authorizedBy()
    {
        return $this->belongsTo(User::class, 'authorized_by');
    }

    public function items()
    {
        return $this->belongsToMany(SaleItem::class, 'sale_incident_items');
    }
}