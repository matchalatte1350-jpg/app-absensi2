<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        Schema::create('divisis', function (Blueprint $table) {
            $table->id('id_divisi');
            $table->string('nama_divisi');
            $table->string('lat_kantor')->nullable();
            $table->string('long_kantor')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('divisis');
    }
};
