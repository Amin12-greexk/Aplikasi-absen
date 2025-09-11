<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Karyawan extends Model
{
    use HasFactory;

    protected $table = 'karyawan';
    protected $primaryKey = 'karyawan_id';

    protected $fillable = [
        'nik', 'nama_lengkap', 'tempat_lahir', 'tanggal_lahir', 'jenis_kelamin',
        'alamat', 'status_perkawinan', 'nomor_telepon', 'email', 'tanggal_masuk',
        'kategori_gaji', 'jam_kerja_masuk', 'jam_kerja_pulang', 'status',
        'departemen_id_saat_ini', 'jabatan_id_saat_ini'
    ];

    public function departemenSaatIni(): BelongsTo
    {
        return $this->belongsTo(Departemen::class, 'departemen_id_saat_ini', 'departemen_id');
    }

    public function jabatanSaatIni(): BelongsTo
    {
        return $this->belongsTo(Jabatan::class, 'jabatan_id_saat_ini', 'jabatan_id');
    }

    public function historiJabatan(): HasMany
    {
        return $this->hasMany(HistoriJabatan::class, 'karyawan_id', 'karyawan_id');
    }

    public function absensi(): HasMany
    {
        return $this->hasMany(Absensi::class, 'karyawan_id', 'karyawan_id');
    }

    public function jadwalShift(): HasMany
    {
        return $this->hasMany(JadwalShift::class, 'karyawan_id', 'karyawan_id');
    }

    public function riwayatGaji(): HasMany
    {
        return $this->hasMany(RiwayatGaji::class, 'karyawan_id', 'karyawan_id');
    }
}
