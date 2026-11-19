<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cuti extends Model
{
    protected $table = 'cutis';

    protected $guarded = [];

    public function karyawan()
    {
        return $this->belongsTo(User::class, 'id_user');
    }
}
