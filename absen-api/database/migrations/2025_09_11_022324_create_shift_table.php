<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('shift', function (Blueprint $table) {
            $table->id('shift_id');
            $table->string('kode_shift', 10)->unique();
            $table->time('jam_masuk')->nullable();
            $table->time('jam_pulang')->nullable();
            $table->boolean('hari_berikutnya')->default(false); // Untuk shift malam
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shift');
    }
};
