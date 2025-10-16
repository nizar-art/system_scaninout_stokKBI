<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Forecast extends Model
{
    use HasFactory;

    protected $table = 'tbl_forecast';

    protected $fillable = [
        'id_part',
        'id_work',
        'hari_kerja',
        'issued_at',
        'forecast_month',
        'PO_pcs',
        'min',
        'max',
        'frequensi_delivery',
    ];

    protected $dates = ['forecast_month'];
    // fk part
    public function part()
    {
        return $this->belongsTo(Part::class, 'id_part');
    }
    public function forecast()
    {
        return $this->hasMany(Forecast::class, 'id_part');
    }
    public function workday()
    {
        return $this->belongsTo(WorkDays::class, foreignKey: 'id_work');
    }


}
