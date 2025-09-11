<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HistoriJabatan extends Model
{
    use HasFactory;

    protected $table = 'histori_jabatan';
    protected $primaryKey = 'histori_id';

    protected $fillable = [
        'karyawan_id', 'departemen_id', 'jabatan_id',
        'tanggal_mulai', 'tanggal_selesai'
    ];

    public function karyawan(): BelongsTo
    {
        return $this->belongsTo(Karyawan::class, 'karyawan_id', 'karyawan_id');
    }

    public function departemen(): BelongsTo
    {
        return $this->belongsTo(Departemen::class, 'departemen_id', 'departemen_id');
    }

    public function jabatan(): BelongsTo
    {
        return $this->belongsTo(Jabatan::class, 'jabatan_id', 'jabatan_id');
    }
}
