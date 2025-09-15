<?php
// app/Models/Absensi.php (UPDATE)

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
        'durasi_lembur_menit', 'status',
        'jam_lembur', 'jenis_hari', 'hadir_6_hari_periode',  // ADDED
        'upah_lembur', 'premi', 'uang_makan', 'total_gaji_tambahan'  // ADDED
    ];

    protected $casts = [
        'tanggal_absensi' => 'date',
        'jam_scan_masuk' => 'datetime',
        'jam_scan_pulang' => 'datetime',
        'jam_lembur' => 'decimal:2',
        'upah_lembur' => 'decimal:2',
        'premi' => 'decimal:2',
        'uang_makan' => 'decimal:2',
        'total_gaji_tambahan' => 'decimal:2',
        'hadir_6_hari_periode' => 'boolean'
    ];

    public function karyawan(): BelongsTo
    {
        return $this->belongsTo(Karyawan::class, 'karyawan_id', 'karyawan_id');
    }

    // Helper methods
    public function isWeekend()
    {
        return in_array($this->tanggal_absensi->dayOfWeek, [0, 6]); // Sunday = 0, Saturday = 6
    }

    public function calculateJamLembur()
    {
        if (!$this->jam_scan_pulang || !$this->jam_scan_masuk) {
            return 0;
        }

        $jamKerja = $this->jam_scan_masuk->diffInHours($this->jam_scan_pulang);
        $jamKerjaNormal = 8; // 8 jam kerja normal
        return max(0, $jamKerja - $jamKerjaNormal);
    }
}