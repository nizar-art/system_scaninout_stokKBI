<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HeadArea extends Model
{
    use HasFactory;
    protected $table = 'tbl_head_area';
    protected $fillable = [
        'nama_area',
        'id_plan',
    ];

    public function plant()
    {
        return $this->belongsTo(Plant::class, 'id_plan');
    }
    public function plan()
    {
        return $this->belongsTo(Plant::class, 'id_plan');
    }

    public function dailystocklogs()
    {
        return $this->hasMany(DailyStockLog::class, 'id_area_head');
    }
}
