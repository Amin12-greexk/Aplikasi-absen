<?php
// app/Models/RiwayatGaji.php (UPDATED)

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

class RiwayatGaji extends Model
{
    use HasFactory;

    protected $table = 'riwayat_gaji';
    protected $primaryKey = 'gaji_id';

    protected $fillable = [
        'karyawan_id', 'periode', 'tipe_periode', 'periode_mulai', 'periode_selesai',
        'gaji_final', 'tanggal_pembayaran', 'period_id'
    ];

    protected $casts = [
        'periode_mulai' => 'date',
        'periode_selesai' => 'date',
        'tanggal_pembayaran' => 'date'
    ];

    public function karyawan(): BelongsTo
    {
        return $this->belongsTo(Karyawan::class, 'karyawan_id', 'karyawan_id');
    }

    public function detailGaji(): HasMany
    {
        return $this->hasMany(DetailGaji::class, 'gaji_id', 'gaji_id');
    }

    public function payrollPeriod(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class, 'period_id', 'period_id');
    }

    // Generate periode string berdasarkan range tanggal
    public function generatePeriodeString()
    {
        if (!$this->periode_mulai || !$this->periode_selesai) {
            return $this->periode; // fallback ke format lama
        }

        $start = $this->periode_mulai;
        $end = $this->periode_selesai;

        switch ($this->tipe_periode) {
            case 'harian':
                return $start->format('d M Y');
            case 'mingguan':
                return $start->format('d M') . ' - ' . $end->format('d M Y');
            case 'bulanan':
                return $start->format('F Y');
            default:
                return $this->periode;
        }
    }

    // app/Models/RiwayatGaji.php
    public function getPeriodeLabelAttribute()
    {
        if ($this->tipe_periode === 'harian') {
            // Format: "15 Jan 2024"
            return Carbon::parse($this->periode_mulai)->format('d M Y');
        } elseif ($this->tipe_periode === 'mingguan') {
            // Format: "15 - 21 Jan 2024"
            $start = Carbon::parse($this->periode_mulai);
            $end = Carbon::parse($this->periode_selesai);
            
            if ($start->month === $end->month) {
                return $start->format('d') . ' - ' . $end->format('d M Y');
            } else {
                return $start->format('d M') . ' - ' . $end->format('d M Y');
            }
        } else {
            // Bulanan: "Januari 2024"
            return Carbon::parse($this->periode_mulai)->locale('id')->translatedFormat('F Y');
        }
    }

    // Add to appends
    protected $appends = ['periode_label'];
}