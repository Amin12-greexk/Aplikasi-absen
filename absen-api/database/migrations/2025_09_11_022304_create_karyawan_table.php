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
        Schema::create('karyawan', function (Blueprint $table) {
            $table->id('karyawan_id');
            $table->string('nik', 20)->unique();
            $table->string('nama_lengkap', 255);
            $table->string('tempat_lahir', 100);
            $table->date('tanggal_lahir');
            $table->enum('jenis_kelamin', ['Laki-laki', 'Perempuan']);
            $table->text('alamat');
            $table->enum('status_perkawinan', ['Belum Menikah', 'Menikah', 'Cerai']);
            $table->string('nomor_telepon', 20);
            $table->string('email', 255)->unique();
            $table->date('tanggal_masuk');
            $table->enum('kategori_gaji', ['Bulanan', 'Harian', 'Borongan']);
            $table->time('jam_kerja_masuk')->nullable();
            $table->time('jam_kerja_pulang')->nullable();
            $table->enum('status', ['Aktif', 'Resign'])->default('Aktif');
            
            $table->foreignId('departemen_id_saat_ini')->constrained('departemen', 'departemen_id');
            $table->foreignId('jabatan_id_saat_ini')->constrained('jabatan', 'jabatan_id');
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('karyawan');
    }
};
