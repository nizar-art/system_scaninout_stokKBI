<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HeadRak extends Model
{
    use HasFactory;

    protected $table = 'tbl_head_rak';

    protected $fillable = [
        'name_rak',
        'status',
    ];

    public function rakstocks()
    {
        return $this->hasMany(RakStock::class, 'id_rak_head');
    }
}
