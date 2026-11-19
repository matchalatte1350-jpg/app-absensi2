<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Gaji extends Model
{
    protected $table = 'gajis';

    protected $fillable = [
        'id_user',
        'periode',
        'hari_hadir',
        'jam_lembur',
        'total_gaji',
        'status',
    ];

    protected $casts = [
        'total_gaji' => 'decimal:2',
    ];

    public function karyawan()
    {
        return $this->belongsTo(User::class, 'id_user');
    }
}
