<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RakStock extends Model
{
    use HasFactory;

    protected $table = 'tbl_rak_stock';
    protected $fillable = ['id_inventory', 'rak_name', 'stok'];

    public function part()
    {
        return $this->belongsTo(Part::class, 'id_inventory');
    }
}
