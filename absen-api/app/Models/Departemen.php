<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Departemen extends Model
{
    use HasFactory;

    protected $table = 'departemen';
    protected $primaryKey = 'departemen_id';

    protected $fillable = ['nama_departemen', 'menggunakan_shift'];

    public function karyawan(): HasMany
    {
        return $this->hasMany(Karyawan::class, 'departemen_id_saat_ini', 'departemen_id');
    }
}
