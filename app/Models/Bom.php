<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Bom extends Model
{
    use HasFactory;

    protected $table = 'tbl_bom';
    protected $fillable = [
        'product_id',
        'component_id',
        'quantity',
        'created_at',
        'updated_at',
        'unit'
    ];
    protected $casts = [
        'quantity' => 'double',
    ];
    public function product()
    {
        return $this->belongsTo(Part::class, 'product_id');
    }
    public function component()
    {
        return $this->belongsTo(Part::class, 'component_id');
    }
}
