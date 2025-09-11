<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Absensi extends Model
{
    use HasFactory;
    
    protected $table = 'absensi';
    protected $primaryKey = 'absensi_id';

    protected $fillable = [
        'karyawan_id', 'tanggal_absensi', 'jam_scan_masuk', 'jam_scan_pulang',
        'durasi_lembur_menit', 'status'
    ];

    public function karyawan(): BelongsTo
    {
        return $this->belongsTo(Karyawan::class, 'karyawan_id', 'karyawan_id');
    }
}
