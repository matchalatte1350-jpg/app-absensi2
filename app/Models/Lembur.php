<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Lembur extends Model
{
    protected $table = 'lemburs'; // Sesuaikan dengan nama tabel migrasi (pakai 's')

    protected $guarded = [];

    public function karyawan()
    {
        return $this->belongsTo(User::class, 'id_user');
    }
}
