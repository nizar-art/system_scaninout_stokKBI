<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use App\Models\Role;
use App\Models\DailyStockLog;

class User extends Authenticatable
{
    use HasApiTokens;
    use HasFactory;
    use Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */

    protected $table = 'tbl_user';
    protected $fillable = [
        'username',
        // 'email',
        'first_name',
        'last_name',
        'password',
        'nik',
        // 'id_role',
    ];
    protected $hidden = [
        'password',
    ];

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'role_user', 'user_id', 'role_id');
    }
    public function dailyStockLog()
    {
        return $this->hasMany(DailyStockLog::class, 'prepared_by', 'id');
    }

    public function headArea()
    {
        return $this->belongsTo(HeadArea::class, 'id_head_area');
    }

    /**
     * Cek apakah user memiliki role tertentu (atau salah satu dari array role)
     * @param string|array $roles
     * @return bool
     */
    public function hasRole($roles)
    {
        $userRoles = $this->roles->pluck('name')->toArray();
        if (is_array($roles)) {
            return count(array_intersect($roles, $userRoles)) > 0;
        }
        return in_array($roles, $userRoles);
    }


}