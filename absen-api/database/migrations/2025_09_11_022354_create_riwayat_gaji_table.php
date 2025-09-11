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
        Schema::create('riwayat_gaji', function (Blueprint $table) {
            $table->id('gaji_id');
            $table->foreignId('karyawan_id')->constrained('karyawan', 'karyawan_id')->onDelete('cascade');
            $table->string('periode', 7); // Format: YYYY-MM
            $table->decimal('gaji_final', 15, 2)->nullable();
            $table->date('tanggal_pembayaran')->nullable();
            $table->timestamps();

            $table->unique(['karyawan_id', 'periode']);
        });
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('riwayat_gaji');
    }
};
