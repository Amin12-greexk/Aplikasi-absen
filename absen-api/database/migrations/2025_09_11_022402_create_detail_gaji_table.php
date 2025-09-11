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
        Schema::create('detail_gaji', function (Blueprint $table) {
            $table->id('detail_gaji_id');
            $table->foreignId('gaji_id')->constrained('riwayat_gaji', 'gaji_id')->onDelete('cascade');
            $table->enum('jenis_komponen', ['Pendapatan', 'Potongan']);
            $table->string('deskripsi', 100);
            $table->decimal('jumlah', 15, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('detail_gaji');
    }
};
