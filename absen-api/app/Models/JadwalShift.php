<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JadwalShift extends Model
{
    use HasFactory;

    protected $table = 'jadwal_shift';
    protected $primaryKey = 'jadwal_id';

    protected $fillable = ['karyawan_id', 'shift_id', 'tanggal_jadwal'];

    public function karyawan(): BelongsTo
    {
        return $this->belongsTo(Karyawan::class, 'karyawan_id', 'karyawan_id');
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class, 'shift_id', 'shift_id');
    }
}
