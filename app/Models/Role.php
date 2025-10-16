<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    use HasFactory;

    protected $table = 'tbl_role';
    protected $fillable = [
        'id',
        'name',
    ];
    public function users()
    {
        return $this->hasMany(User::class, 'id_role', 'id');
    }
}
