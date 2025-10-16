<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use HasFactory;
    protected $table = 'tbl_customer';
    protected $fillable = [
        'name',
        'username'
    ];

    public function part(){
        return $this->hasMany(Part::class, 'id_customer', 'id');
    }

    public function parts()
    {
        return $this->hasMany(Part::class, 'id_customer');
    }
}
