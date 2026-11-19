<?php

namespace Database\Seeders;

use App\Models\Absensi;
use App\Models\Cuti;
use App\Models\Divisi;
use App\Models\Gaji;
use App\Models\Lembur;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $divisi = Divisi::firstOrCreate(
            ['nama_divisi' => 'Teknologi Informasi'],
            [
                'lat_kantor' => '-5.450000',
                'long_kantor' => '105.266670',
            ]
        );

        $manajer = User::updateOrCreate(
            ['username' => 'manajer'],
            [
                'nama_lengkap' => 'Bapak Manajer',
                'kode_karyawan' => 'EMP001',
                'no_telp' => '081234567890',
                'password' => Hash::make('password123'),
                'role' => 'manajer',
                'status_kerja' => 'Aktif',
                'id_divisi' => $divisi->id_divisi,
            ]
        );

        $kadiv = User::updateOrCreate(
            ['username' => 'kadiv'],
            [
                'nama_lengkap' => 'Kepala Divisi IT',
                'kode_karyawan' => 'EMP002',
                'no_telp' => '081234567891',
                'password' => Hash::make('password123'),
                'role' => 'kepala_divisi',
                'status_kerja' => 'Aktif',
                'id_divisi' => $divisi->id_divisi,
            ]
        );

        $karyawan = User::updateOrCreate(
            ['username' => 'karyawan'],
            [
                'nama_lengkap' => 'Muhammad Animar',
                'kode_karyawan' => 'EMP003',
                'no_telp' => '089876543210',
                'password' => Hash::make('password123'),
                'role' => 'karyawan',
                'status_kerja' => 'Aktif',
                'id_divisi' => $divisi->id_divisi,
            ]
        );

        $this->call(KaryawanSeeder::class);

        Absensi::firstOrCreate(
            [
                'id_user' => $karyawan->id_user,
                'tanggal' => now()->toDateString(),
                'status_kehadiran' => 'Hadir',
            ],
            ['keterangan' => 'Data contoh presensi']
        );

        Cuti::firstOrCreate(
            [
                'id_user' => $karyawan->id_user,
                'jenis_cuti' => 'Cuti Tahunan',
                'tanggal_mulai' => now()->toDateString(),
            ],
            [
                'tanggal_selesai' => now()->addDays(2)->toDateString(),
                'alasan' => 'Keperluan keluarga',
                'status' => 'Pending',
            ]
        );

        Lembur::firstOrCreate(
            [
                'id_user' => $karyawan->id_user,
                'tanggal_lembur' => now()->toDateString(),
                'durasi' => '2 Jam',
            ],
            [
                'keterangan' => 'Penyelesaian laporan bulanan',
                'status' => 'Pending',
            ]
        );

        Gaji::updateOrCreate(
            [
                'id_user' => $karyawan->id_user,
                'periode' => now()->format('Y-m'),
            ],
            [
                'hari_hadir' => 20,
                'jam_lembur' => 2,
                'total_gaji' => 4500000,
                'status' => 'Dibayarkan',
            ]
        );

        Gaji::updateOrCreate(
            [
                'id_user' => $manajer->id_user,
                'periode' => now()->format('Y-m'),
            ],
            [
                'hari_hadir' => 22,
                'jam_lembur' => 4,
                'total_gaji' => 8500000,
                'status' => 'Dibayarkan',
            ]
        );

        Gaji::updateOrCreate(
            [
                'id_user' => $kadiv->id_user,
                'periode' => now()->format('Y-m'),
            ],
            [
                'hari_hadir' => 21,
                'jam_lembur' => 1,
                'total_gaji' => 6500000,
                'status' => 'Dibayarkan',
            ]
        );
    }
}
