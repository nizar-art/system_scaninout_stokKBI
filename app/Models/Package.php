<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Package extends Model
{
    use HasFactory;

    protected $table = 'tbl_package';
    protected $fillable = ['type_pkg', 'qty', 'id_part'];

    public function part()
    {
        return $this->belongsTo(Part::class, 'id_part');
    }
}
