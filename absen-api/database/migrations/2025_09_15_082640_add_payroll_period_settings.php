<?php
// database/migrations/2025_09_15_200001_add_payroll_period_settings.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Update karyawan table untuk payroll period setting
        Schema::table('karyawan', function (Blueprint $table) {
            $table->enum('periode_gaji', ['mingguan', 'bulanan', 'harian'])->default('bulanan')->after('kategori_gaji');
            $table->date('tanggal_gaji_terakhir')->nullable()->after('periode_gaji');
        });

        // Update riwayat_gaji table untuk support different periods
        Schema::table('riwayat_gaji', function (Blueprint $table) {
            $table->enum('tipe_periode', ['harian', 'mingguan', 'bulanan'])->default('bulanan')->after('periode');
            $table->date('periode_mulai')->after('tipe_periode');
            $table->date('periode_selesai')->after('periode_mulai');
            
            // Drop unique constraint lama
            $table->dropUnique(['karyawan_id', 'periode']);
            
            // Add new unique constraint
            $table->unique(['karyawan_id', 'periode_mulai', 'periode_selesai'], 'unique_karyawan_periode_range');
        });

        // Create payroll_periods table for predefined periods
        Schema::create('payroll_periods', function (Blueprint $table) {
            $table->id('period_id');
            $table->string('nama_periode')->comment('Contoh: Minggu 1 September 2025');
            $table->enum('tipe_periode', ['harian', 'mingguan', 'bulanan']);
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai');
            $table->boolean('is_closed')->default(false)->comment('Apakah periode sudah ditutup');
            $table->date('tanggal_pembayaran')->nullable();
            $table->text('keterangan')->nullable();
            $table->timestamps();
            
            $table->index(['tipe_periode', 'tanggal_mulai']);
            $table->index(['is_closed', 'tanggal_pembayaran']);
        });
    }

    public function down()
    {
        Schema::table('karyawan', function (Blueprint $table) {
            $table->dropColumn(['periode_gaji', 'tanggal_gaji_terakhir']);
        });

        Schema::table('riwayat_gaji', function (Blueprint $table) {
            $table->dropUnique('unique_karyawan_periode_range');
            $table->dropColumn(['tipe_periode', 'periode_mulai', 'periode_selesai']);
            $table->unique(['karyawan_id', 'periode']);
        });

        Schema::dropIfExists('payroll_periods');
    }
};