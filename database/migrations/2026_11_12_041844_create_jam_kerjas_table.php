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
        Schema::create('jam_kerjas', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
        });

        Schema::create('setting_jams', function (Blueprint $table) {
            $table->id();
            $table->time('jam_masuk_mulai');
            $table->time('jam_masuk_akhir');
            $table->time('jam_pulang_mulai');
            $table->time('jam_pulang_akhir');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('setting_jams');
        Schema::dropIfExists('jam_kerjas');
    }
};
