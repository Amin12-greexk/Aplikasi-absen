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
        Schema::create('jadwal_shift', function (Blueprint $table) {
            $table->id('jadwal_id');
            $table->foreignId('karyawan_id')->constrained('karyawan', 'karyawan_id')->onDelete('cascade');
            $table->foreignId('shift_id')->constrained('shift', 'shift_id');
            $table->date('tanggal_jadwal');
            $table->timestamps();

            $table->unique(['karyawan_id', 'tanggal_jadwal']);
        });
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('jadwal_shift');
    }
};
