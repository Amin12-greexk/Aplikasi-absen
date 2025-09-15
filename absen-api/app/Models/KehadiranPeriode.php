<?php
// app/Models/KehadiranPeriode.php (NEW)

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KehadiranPeriode extends Model
{
    use HasFactory;

    protected $table = 'kehadiran_periode';
    protected $primaryKey = 'kehadiran_id';

    protected $fillable = [
        'karyawan_id', 'periode', 'total_hari_hadir', 'memenuhi_syarat_premi'
    ];

    protected $casts = [
        'memenuhi_syarat_premi' => 'boolean'
    ];

    public function karyawan(): BelongsTo
    {
        return $this->belongsTo(Karyawan::class, 'karyawan_id', 'karyawan_id');
    }

    public function updateKehadiran($tambahHari = 1)
    {
        $this->total_hari_hadir += $tambahHari;
        $this->memenuhi_syarat_premi = $this->total_hari_hadir >= 6;
        $this->save();
    }

    public static function updateOrCreateKehadiran($karyawanId, $periode)
    {
        $kehadiran = static::firstOrCreate(
            ['karyawan_id' => $karyawanId, 'periode' => $periode],
            ['total_hari_hadir' => 0, 'memenuhi_syarat_premi' => false]
        );

        $kehadiran->updateKehadiran();
        return $kehadiran;
    }
}