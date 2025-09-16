<?php
// app/Models/AttendanceLog.php (NEW)

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceLog extends Model
{
    use HasFactory;

    protected $table = 'attendance_log';
    protected $primaryKey = 'log_id';
    public $timestamps = true;

    protected $fillable = [
        'device_sn', 'pin', 'scan_time', 'verify_mode', 
        'inout_mode', 'device_ip', 'is_processed', 'processed_at'
    ];

    protected $casts = [
        'scan_time' => 'datetime',
        'processed_at' => 'datetime',
        'is_processed' => 'boolean'
    ];

    public function karyawan(): BelongsTo
    {
        return $this->belongsTo(Karyawan::class, 'pin', 'pin_fingerprint');
    }

    // Scopes
    public function scopeUnprocessed($query)
    {
        return $query->where('is_processed', false);
    }

    public function scopeCheckIn($query)
    {
        return $query->where('inout_mode', 1);
    }

    public function scopeCheckOut($query)
    {
        return $query->where('inout_mode', 2);
    }

    public function scopeByDate($query, $date)
    {
        return $query->whereDate('scan_time', $date);
    }

    public function scopeByPin($query, $pin)
    {
        return $query->where('pin', $pin);
    }

    // Helper methods
    public function isCheckIn()
    {
        return $this->inout_mode === 1;
    }

    public function isCheckOut()
    {
        return $this->inout_mode === 2;
    }

    public function getVerifyMethodAttribute()
    {
        return $this->verify_mode === 1 ? 'Fingerprint' : 'Password';
    }

    public function markAsProcessed()
    {
        $this->update([
            'is_processed' => true,
            'processed_at' => now()
        ]);
    }
}