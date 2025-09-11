<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Jabatan extends Model
{
    use HasFactory;

    protected $table = 'jabatan';
    protected $primaryKey = 'jabatan_id';
    protected $fillable = ['nama_jabatan'];

    public function karyawan(): HasMany
    {
        return $this->hasMany(Karyawan::class, 'jabatan_id_saat_ini', 'jabatan_id');
    }
}
