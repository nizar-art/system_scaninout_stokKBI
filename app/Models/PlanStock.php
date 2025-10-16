<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlanStock extends Model
{
    use HasFactory;
    protected $table = 'tbl_plan_stock_log';
    protected $fillable = [
        'id_inventory',
        'plan_stock_before',
        'plan_stock_after',
        'created_at',
        'updated_at',
    ];

    public function inventory()
    {
        return $this->belongsTo(Inventory::class, 'id_inventory');
    }
}
