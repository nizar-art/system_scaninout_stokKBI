<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Area extends Model
{
    use HasFactory;

    protected $table = 'tbl_area';
    protected $fillable = [
        'id',
        'id_plan',
        'nama_area',
    ];
    
    public function plant()
    {
        return $this->belongsTo(Plant::class, 'id_plan');
    }
}
