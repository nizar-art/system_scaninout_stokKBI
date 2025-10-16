<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Plant extends Model
{
    use HasFactory;

    protected $table = 'tbl_plan';
    protected $fillable = [
        'name',
    ];

    public function areas()
    {
        return $this->hasMany(Area::class, 'id_plan');
    }
    public function headAreas()
    {
        return $this->hasMany(HeadArea::class, 'id_plan');
    }

}
