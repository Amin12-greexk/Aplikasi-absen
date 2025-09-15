<?php
// app/Models/HariLibur.php (NEW)

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class HariLibur extends Model
{
    use HasFactory;

    protected $table = 'hari_libur';
    protected $primaryKey = 'libur_id';

    protected $fillable = [
        'tanggal', 'nama_libur', 'jenis', 'is_active'
    ];

    protected $casts = [
        'tanggal' => 'date',
        'is_active' => 'boolean'
    ];

    public static function getJenisHari($tanggal)
    {
        $date = Carbon::parse($tanggal);
        
        // Cek apakah tanggal merah
        $hariLibur = static::where('tanggal', $date->format('Y-m-d'))
            ->where('is_active', true)
            ->first();
            
        if ($hariLibur && $hariLibur->jenis === 'tanggal_merah') {
            return 'tanggal_merah';
        }
        
        // Cek weekend (Sabtu = 6, Minggu = 0)
        if (in_array($date->dayOfWeek, [0, 6])) {
            return 'weekend';
        }
        
        return 'weekday';
    }

    public static function isTanggalMerah($tanggal)
    {
        return static::getJenisHari($tanggal) === 'tanggal_merah';
    }

    public static function isWeekend($tanggal)
    {
        return static::getJenisHari($tanggal) === 'weekend';
    }
}