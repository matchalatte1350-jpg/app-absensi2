<?php

namespace App\Models;

// Tambahkan baris-baris use di bawah ini
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $primaryKey = 'id_user';

    protected $fillable = [
        'nama_lengkap',
        'username',
        'kode_karyawan',
        'password',
        'role',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    public function absensis()
    {
        return $this->hasMany(Absensi::class, 'id_user');
    }

    public function cutis()
    {
        return $this->hasMany(Cuti::class, 'id_user');
    }

    public function lemburs()
    {
        return $this->hasMany(Lembur::class, 'id_user');
    }

    public function gajis()
    {
        return $this->hasMany(Gaji::class, 'id_user');
    }
}
