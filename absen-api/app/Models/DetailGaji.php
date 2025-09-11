<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DetailGaji extends Model
{
    use HasFactory;

    protected $table = 'detail_gaji';
    protected $primaryKey = 'detail_gaji_id';

    protected $fillable = ['gaji_id', 'jenis_komponen', 'deskripsi', 'jumlah'];

    public function riwayatGaji(): BelongsTo
    {
        return $this->belongsTo(RiwayatGaji::class, 'gaji_id', 'gaji_id');
    }
}
