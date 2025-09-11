<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RiwayatGaji extends Model
{
    use HasFactory;

    protected $table = 'riwayat_gaji';
    protected $primaryKey = 'gaji_id';

    protected $fillable = ['karyawan_id', 'periode', 'gaji_final', 'tanggal_pembayaran'];

    public function karyawan(): BelongsTo
    {
        return $this->belongsTo(Karyawan::class, 'karyawan_id', 'karyawan_id');
    }

    public function detailGaji(): HasMany
    {
        return $this->hasMany(DetailGaji::class, 'gaji_id', 'gaji_id');
    }
}
