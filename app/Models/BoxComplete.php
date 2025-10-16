<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BoxComplete extends Model
{
    use HasFactory;
    protected $table = 'tbl_box_complete';
    protected $fillable = [
        'qty_per_box',
        'qty_box',
        'total'
    ];

    public function dailyStockLog()
    {
        return $this->hasMany(DailyStockLog::class, 'id_box_complete');
    }
}
