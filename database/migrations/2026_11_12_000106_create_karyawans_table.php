<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('karyawans', function (Blueprint $table) {
            $table->id();
            $table->string('nama_lengkap');
            $table->string('kode_karyawan')->unique();
            $table->string('username')->unique();
            $table->string('no_telp')->nullable();
            $table->string('password');
            $table->enum('role', ['admin', 'staff', 'hr'])->default('staff');
            $table->enum('status_kerja', ['aktif', 'nonaktif'])->default('aktif');
            $table->unsignedBigInteger('id_divisi');
            $table->string('foto_profil')->nullable();
            $table->timestamps();

            $table->foreign('id_divisi')->references('id_divisi')->on('divisis')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('karyawans');
    }
};
