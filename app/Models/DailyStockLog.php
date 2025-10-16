<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


class DailyStockLog extends Model
{
    use HasFactory;
    protected $table = 'tbl_daily_stock_logs';
    protected $fillable = [
        'id',
        'id_inventory',
        'id_box_complete',
        'id_box_uncomplete',
        'id_area_head',
        'stock_per_day',
        'prepared_by',
        'Total_qty',
        'status',
        'created_at',
        'updated_at',
        'date'
    ];

    // DailyStockLog.php
    public function user()
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }
    public function boxComplete()
    {
        return $this->belongsTo(BoxComplete::class, 'id_box_complete');
    }
    public function boxUncomplete()
    {
        return $this->belongsTo(BoxUncomplete::class, 'id_box_uncomplete');
    }
    public function part()
    {
        return $this->belongsTo(Part::class, 'id_inventory');
    }
    // public function parts()
    // {
    //     return $this->belongsTo(Part::class, 'id_inventory');
    // }
    public function areaHead()
    {
        return $this->belongsTo(HeadArea::class, 'id_area_head');
    }

    public function plant()
    {
        return $this->belongsTo(Plant::class, 'id_plan');
    }

    public function getForecastMinAttribute()
    {
        if (!$this->part || !$this->created_at)
            return null;

        $month = $this->created_at->format('m');
        $year = $this->created_at->format('Y');

        return $this->part->forecasts()
            ->whereYear('forecast_month', $year)
            ->whereMonth('forecast_month', $month)
            ->min('min');
    }

    public function getForecastMaxAttribute()
    {
        if (!$this->part || !$this->created_at)
            return null;

        $month = $this->created_at->format('m');
        $year = $this->created_at->format('Y');

        return $this->part->forecasts()
            ->whereYear('forecast_month', $year)
            ->whereMonth('forecast_month', $month)
            ->max('max');
    }

    public function checkForecast()
    {
        if (!$this->id_inventory || !$this->created_at) {
            return false;
        }

        $month = $this->created_at->format('Y-m');
        return $this->part->forecasts()
            ->whereRaw("DATE_FORMAT(forecast_month, '%Y-%m') = ?", [$month])
            ->exists();
    }

    // Accessor untuk memudahkan pengecekan di blade
    public function getHasForecastAttribute()
    {
        return $this->checkForecast();
    }
}