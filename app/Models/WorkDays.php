<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WorkDays extends Model
{
    use HasFactory;
    protected $table = 'tbl_working_days';
    protected $fillable = [
        'month',
        'hari_kerja',
    ];
    protected $casts = [
        'month' => 'date',
    ];

    public function forecast()
    {
        return $this->hasMany(Forecast::class, 'id_work');
    }

}
