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
        Schema::create('absensi', function (Blueprint $table) {
            $table->id('absensi_id');
            $table->foreignId('karyawan_id')->constrained('karyawan', 'karyawan_id')->onDelete('cascade');
            $table->date('tanggal_absensi');
            $table->dateTime('jam_scan_masuk')->nullable();
            $table->dateTime('jam_scan_pulang')->nullable();
            $table->integer('durasi_lembur_menit')->nullable()->default(0);
            $table->enum('status', ['Hadir', 'Terlambat', 'Izin', 'Cuti', 'Alpha', 'Libur']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('absensi');
    }
};
