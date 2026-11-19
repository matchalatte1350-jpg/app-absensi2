<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Karyawan extends Model
{
    use HasFactory;

    protected $table = 'karyawans';

    protected $fillable = [
        'nama_lengkap',
        'kode_karyawan',
        'username',
        'no_telp',
        'password',
        'role',
        'status_kerja',
        'id_divisi',
        'foto_profil',
    ];

    public function divisi()
    {
        return $this->belongsTo(Divisi::class, 'id_divisi', 'id_divisi');
    }
}
