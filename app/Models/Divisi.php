<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Divisi extends Model
{
    protected $table = 'divisis';

    protected $primaryKey = 'id_divisi';

    protected $fillable = [
        'nama_divisi',
        'lat_kantor',
        'long_kantor',
    ];

    public function users()
    {
        return $this->hasMany(User::class, 'id_divisi', 'id_divisi');
    }
}
