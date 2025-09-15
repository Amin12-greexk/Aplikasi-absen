<?php
// app/Models/SettingGaji.php (NEW)

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SettingGaji extends Model
{
    use HasFactory;

    protected $table = 'setting_gaji';
    protected $primaryKey = 'setting_id';

    protected $fillable = [
        'premi_produksi', 'premi_staff',
        'uang_makan_produksi_weekday', 'uang_makan_produksi_weekend_5_10', 'uang_makan_produksi_weekend_10_20',
        'uang_makan_staff_weekday', 'uang_makan_staff_weekend_5_10', 'uang_makan_staff_weekend_10_20',
        'tarif_lembur_produksi_per_jam', 'tarif_lembur_staff_per_jam',
        'is_active'
    ];

    protected $casts = [
        'premi_produksi' => 'decimal:2',
        'premi_staff' => 'decimal:2',
        'uang_makan_produksi_weekday' => 'decimal:2',
        'uang_makan_produksi_weekend_5_10' => 'decimal:2',
        'uang_makan_produksi_weekend_10_20' => 'decimal:2',
        'uang_makan_staff_weekday' => 'decimal:2',
        'uang_makan_staff_weekend_5_10' => 'decimal:2',
        'uang_makan_staff_weekend_10_20' => 'decimal:2',
        'tarif_lembur_produksi_per_jam' => 'decimal:2',
        'tarif_lembur_staff_per_jam' => 'decimal:2',
        'is_active' => 'boolean'
    ];

    public static function getActiveSetting()
    {
        return static::where('is_active', true)->first();
    }

    public function getPremiByRole($role)
    {
        return $role === 'produksi' ? $this->premi_produksi : $this->premi_staff;
    }

    public function getTarifLemburByRole($role)
    {
        return $role === 'produksi' ? $this->tarif_lembur_produksi_per_jam : $this->tarif_lembur_staff_per_jam;
    }

    public function getUangMakan($role, $jenisHari, $jamLembur = 0)
    {
        if ($jenisHari === 'weekday') {
            return $role === 'produksi' ? $this->uang_makan_produksi_weekday : $this->uang_makan_staff_weekday;
        }

        // Weekend atau tanggal merah
        if ($jamLembur >= 5 && $jamLembur <= 10) {
            return $role === 'produksi' ? $this->uang_makan_produksi_weekend_5_10 : $this->uang_makan_staff_weekend_5_10;
        } elseif ($jamLembur > 10 && $jamLembur <= 20) {
            return $role === 'produksi' ? $this->uang_makan_produksi_weekend_10_20 : $this->uang_makan_staff_weekend_10_20;
        }

        return 0;
    }
}