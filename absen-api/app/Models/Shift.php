<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shift extends Model
{
    use HasFactory;
    
    protected $table = 'shift';
    protected $primaryKey = 'shift_id';

    protected $fillable = ['kode_shift', 'jam_masuk', 'jam_pulang', 'hari_berikutnya'];
    
    public function jadwalShift(): HasMany
    {
        return $this->hasMany(JadwalShift::class, 'shift_id', 'shift_id');
    }
}
