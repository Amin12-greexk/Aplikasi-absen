<?php
// app/Models/PayrollPeriod.php (NEW)

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

class PayrollPeriod extends Model
{
    use HasFactory;

    protected $table = 'payroll_periods';
    protected $primaryKey = 'period_id';

    protected $fillable = [
        'nama_periode', 'tipe_periode', 'tanggal_mulai', 'tanggal_selesai',
        'is_closed', 'tanggal_pembayaran', 'keterangan'
    ];

    protected $casts = [
        'tanggal_mulai' => 'date',
        'tanggal_selesai' => 'date',
        'tanggal_pembayaran' => 'date',
        'is_closed' => 'boolean'
    ];

    public function riwayatGaji(): HasMany
    {
        return $this->hasMany(RiwayatGaji::class, 'period_id', 'period_id');
    }

    // Generate periods untuk tahun tertentu
    public static function generatePeriodsForYear($year, $tipe_periode = 'mingguan')
    {
        $periods = [];
        
        switch ($tipe_periode) {
            case 'mingguan':
                $periods = self::generateWeeklyPeriods($year);
                break;
            case 'bulanan':
                $periods = self::generateMonthlyPeriods($year);
                break;
            case 'harian':
                // Biasanya tidak praktis, tapi bisa untuk kasus khusus
                $periods = self::generateDailyPeriods($year);
                break;
        }

        foreach ($periods as $period) {
            self::create($period);
        }

        return $periods;
    }

    private static function generateWeeklyPeriods($year)
    {
        $periods = [];
        $start = Carbon::create($year, 1, 1)->startOfWeek(); // Mulai dari Senin
        $endOfYear = Carbon::create($year, 12, 31);

        $weekNumber = 1;
        while ($start->year <= $year) {
            $end = $start->copy()->endOfWeek(); // Sampai Minggu
            
            // Jika end melewati tahun, set ke akhir tahun
            if ($end->year > $year) {
                $end = $endOfYear;
            }

            $periods[] = [
                'nama_periode' => "Minggu {$weekNumber} " . $start->format('M Y'),
                'tipe_periode' => 'mingguan',
                'tanggal_mulai' => $start->format('Y-m-d'),
                'tanggal_selesai' => $end->format('Y-m-d'),
                'is_closed' => false
            ];

            $start->addWeek();
            $weekNumber++;

            // Reset week number setiap bulan baru
            if ($start->day <= 7) {
                $weekNumber = 1;
            }
        }

        return $periods;
    }

    private static function generateMonthlyPeriods($year)
    {
        $periods = [];
        
        for ($month = 1; $month <= 12; $month++) {
            $start = Carbon::create($year, $month, 1);
            $end = $start->copy()->endOfMonth();
            
            $periods[] = [
                'nama_periode' => $start->format('F Y'),
                'tipe_periode' => 'bulanan',
                'tanggal_mulai' => $start->format('Y-m-d'),
                'tanggal_selesai' => $end->format('Y-m-d'),
                'is_closed' => false
            ];
        }

        return $periods;
    }

    private static function generateDailyPeriods($year, $month = null)
    {
        $periods = [];
        
        if ($month) {
            // Generate untuk bulan tertentu
            $start = Carbon::create($year, $month, 1);
            $end = $start->copy()->endOfMonth();
        } else {
            // Generate untuk setahun (tidak recommended)
            $start = Carbon::create($year, 1, 1);
            $end = Carbon::create($year, 12, 31);
        }

        $current = $start->copy();
        while ($current <= $end) {
            $periods[] = [
                'nama_periode' => $current->format('d F Y'),
                'tipe_periode' => 'harian',
                'tanggal_mulai' => $current->format('Y-m-d'),
                'tanggal_selesai' => $current->format('Y-m-d'),
                'is_closed' => false
            ];
            
            $current->addDay();
        }

        return $periods;
    }

    // Get current active period
    public static function getCurrentPeriod($tipe_periode = 'mingguan')
    {
        $today = Carbon::today();
        
        return self::where('tipe_periode', $tipe_periode)
            ->where('tanggal_mulai', '<=', $today)
            ->where('tanggal_selesai', '>=', $today)
            ->where('is_closed', false)
            ->first();
    }

    // Get available periods untuk payroll generation
    public static function getAvailablePeriodsForPayroll($tipe_periode = 'mingguan')
    {
        return self::where('tipe_periode', $tipe_periode)
            ->where('tanggal_selesai', '<=', Carbon::today())
            ->where('is_closed', false)
            ->orderBy('tanggal_mulai', 'desc')
            ->get();
    }

    // Close periode (tidak bisa generate payroll lagi)
    public function closePeriod($tanggal_pembayaran = null)
    {
        $this->update([
            'is_closed' => true,
            'tanggal_pembayaran' => $tanggal_pembayaran ?: Carbon::today()
        ]);
    }
}