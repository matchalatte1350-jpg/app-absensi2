<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        Schema::create('lemburs', function (Blueprint $table) {
            $table->id();
            // foreign key ke tabel users (kolom id_user di tabel users)
            $table->foreignId('id_user')
                ->constrained('users', 'id_user')
                ->onDelete('cascade');

            $table->date('tanggal_lembur');
            $table->string('durasi');               // estimasi durasi, contoh: "2 Jam"
            $table->text('keterangan');             // keterangan pekerjaan lembur
            $table->enum('status', ['Pending', 'Disetujui', 'Ditolak'])
                ->default('Pending');             // status persetujuan
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lemburs');
    }
};
