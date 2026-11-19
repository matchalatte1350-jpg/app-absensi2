<?php

namespace Database\Seeders;

use App\Models\Divisi;
use App\Models\Karyawan;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class KaryawanSeeder extends Seeder
{
    public function run(): void
    {
        $idDivisi = Divisi::where('nama_divisi', 'Teknologi Informasi')->value('id_divisi');

        Karyawan::updateOrCreate(
            ['username' => 'manajer'],
            [
                'nama_lengkap' => 'Bapak Manajer',
                'kode_karyawan' => 'EMP001',
                'no_telp' => '081234567890',
                'password' => Hash::make('password123'),
                'role' => 'admin',
                'status_kerja' => 'aktif',
                'id_divisi' => $idDivisi,
                'foto_profil' => null,
            ]
        );

        Karyawan::updateOrCreate(
            ['username' => 'kadiv'],
            [
                'nama_lengkap' => 'Ibu Kepala Divisi',
                'kode_karyawan' => 'EMP002',
                'no_telp' => '081234567891',
                'password' => Hash::make('password123'),
                'role' => 'hr',
                'status_kerja' => 'aktif',
                'id_divisi' => $idDivisi,
                'foto_profil' => null,
            ]
        );

        Karyawan::updateOrCreate(
            ['username' => 'karyawan'],
            [
                'nama_lengkap' => 'Budi Karyawan',
                'kode_karyawan' => 'EMP003',
                'no_telp' => '089876543210',
                'password' => Hash::make('password123'),
                'role' => 'staff',
                'status_kerja' => 'aktif',
                'id_divisi' => $idDivisi,
                'foto_profil' => null,
            ]
        );
    }
}
