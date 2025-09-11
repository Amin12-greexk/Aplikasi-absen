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
        Schema::create('histori_jabatan', function (Blueprint $table) {
            $table->id('histori_id');
            $table->foreignId('karyawan_id')->constrained('karyawan', 'karyawan_id')->onDelete('cascade');
            $table->foreignId('departemen_id')->constrained('departemen', 'departemen_id');
            $table->foreignId('jabatan_id')->constrained('jabatan', 'jabatan_id');
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai')->nullable();
            $table->timestamps();
        });
    }
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('histori_jabatan');
    }
};
