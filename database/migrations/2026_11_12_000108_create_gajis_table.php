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
        Schema::create('gajis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_user')
                ->constrained('users', 'id_user')
                ->onDelete('cascade');
            $table->string('periode');
            $table->unsignedInteger('hari_hadir')->default(0);
            $table->unsignedInteger('jam_lembur')->default(0);
            $table->decimal('total_gaji', 15, 2)->default(0);
            $table->string('status')->default('Dibayarkan');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gajis');
    }
};
