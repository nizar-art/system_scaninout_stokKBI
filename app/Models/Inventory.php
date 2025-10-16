<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Inventory extends Model
{
    use HasFactory;

    protected $table = 'tbl_inventory';

    protected $fillable = [
        'id_part',
        'act_stock',
        'plan_stock',
        'remark',
        'note_remark',
        'date'
    ];


    // Relasi ke Part
    public function part()
    {
        return $this->belongsTo(Part::class, 'id_part');
    }

    // Relasi ke Category
    public function category()
    {
        return $this->belongsTo(Category::class, 'id_category');
    }
}