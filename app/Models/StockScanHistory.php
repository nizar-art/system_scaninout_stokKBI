<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockScanHistory extends Model
{
    use HasFactory;

    protected $table = 'tbl_stock_scan_histories';

    protected $fillable = [
        'id_inventory',
        'id_daily_stock_log',
        'user_id',
        'qrcode_raw',
        'stok_inout',
        'status',
        'scanned_at',
    ];

    protected $casts = [
        'scanned_at' => 'datetime',
    ];

    // 🔹 Relasi ke Part (tbl_part)
    public function part()
    {
        return $this->belongsTo(Part::class, 'id_inventory');
    }

    // 🔹 Relasi ke Daily Stock Log (tbl_daily_stock_logs)
    public function dailyStockLog()
    {
        return $this->belongsTo(DailyStockLog::class, 'id_daily_stock_log');
    }

    // 🔹 Relasi ke User (tbl_user)
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
