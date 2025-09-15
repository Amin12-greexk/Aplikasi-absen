<?php
// app/Models/Karyawan.php (UPDATE)

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Notifications\Notifiable;

class Karyawan extends Authenticatable 
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $table = 'karyawan';
    protected $primaryKey = 'karyawan_id';

    protected $fillable = [
        'nik', 'nama_lengkap', 'tempat_lahir', 'tanggal_lahir', 'jenis_kelamin',
        'alamat', 'status_perkawinan', 'nomor_telepon', 'email', 'password', 'role',
        'tanggal_masuk', 'kategori_gaji', 'jam_kerja_masuk', 'jam_kerja_pulang', 
        'status', 'departemen_id_saat_ini', 'jabatan_id_saat_ini',
        'pin_fingerprint', 'role_karyawan'  // ADDED
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'tanggal_lahir' => 'date',
        'tanggal_masuk' => 'date'
    ];

    // Existing relationships
    public function departemenSaatIni(): BelongsTo
    {
        return $this->belongsTo(Departemen::class, 'departemen_id_saat_ini');
    }

    public function jabatanSaatIni(): BelongsTo
    {
        return $this->belongsTo(Jabatan::class, 'jabatan_id_saat_ini');
    }

    public function historiJabatan(): HasMany
    {
        return $this->hasMany(HistoriJabatan::class, 'karyawan_id');
    }

    public function absensi(): HasMany
    {
        return $this->hasMany(Absensi::class, 'karyawan_id');
    }

    public function jadwalShift(): HasMany
    {
        return $this->hasMany(JadwalShift::class, 'karyawan_id');
    }

    public function riwayatGaji(): HasMany
    {
        return $this->hasMany(RiwayatGaji::class, 'karyawan_id');
    }

    // NEW relationships for fingerprint integration
    public function attendanceLogs(): HasMany
    {
        return $this->hasMany(AttendanceLog::class, 'pin', 'pin_fingerprint');
    }

    public function kehadiranPeriode(): HasMany
    {
        return $this->hasMany(KehadiranPeriode::class, 'karyawan_id');
    }

    // Helper methods
    public function getKehadiranBulanIni()
    {
        return $this->kehadiranPeriode()
            ->where('periode', now()->format('Y-m'))
            ->first();
    }

    public function isMemenuiSyaratPremi($periode = null)
    {
        $periode = $periode ?? now()->format('Y-m');
        $kehadiran = $this->kehadiranPeriode()->where('periode', $periode)->first();
        return $kehadiran ? $kehadiran->memenuhi_syarat_premi : false;
    }
}