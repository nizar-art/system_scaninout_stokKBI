<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ImportLog extends Model
{
    use HasFactory;

    protected $table = 'import_logs';
    protected $fillable = [
        'file_path',
        'user_id',
        'status', // processing, completed, failed
        'logs',
    ];
}
