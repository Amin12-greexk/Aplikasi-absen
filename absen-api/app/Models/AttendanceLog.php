<?php
// app/Models/AttendanceLog.php (UPDATED for att_log table structure)

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceLog extends Model
{
    use HasFactory;

    protected $table = 'att_log'; // Changed table name
    
    // Composite primary key - Laravel doesn't support this natively
    // We'll use sn + scan_date + pin as unique identifier
    public $incrementing = false;
    protected $primaryKey = ['sn', 'scan_date', 'pin'];
    public $timestamps = false; // att_log table doesn't have created_at/updated_at

    protected $fillable = [
        'sn',           // device serial number (was device_sn)
        'scan_date',    // scan datetime (was scan_time) 
        'pin',          // employee pin (same)
        'verifymode',   // verification mode (was verify_mode)
        'inoutmode',    // in/out mode (was inout_mode)
        'device_ip',    // device IP (same)
        'is_processed', // add this for processing tracking
        'processed_at'  // add this for processing timestamp
    ];

    protected $casts = [
        'scan_date' => 'datetime',
        'processed_at' => 'datetime',
        'is_processed' => 'boolean'
    ];

    /**
     * Override getKeyName for composite key
     */
    public function getKeyName()
    {
        return ['sn', 'scan_date', 'pin'];
    }

    /**
     * Override setKeysForSaveQuery for composite key
     */
    protected function setKeysForSaveQuery($query)
    {
        $keys = $this->getKeyName();
        if (!is_array($keys)) {
            return parent::setKeysForSaveQuery($query);
        }

        foreach ($keys as $keyName) {
            $query->where($keyName, '=', $this->getKeyForSaveQuery($keyName));
        }

        return $query;
    }

    /**
     * Override getKeyForSaveQuery for composite key
     */
    protected function getKeyForSaveQuery($keyName = null)
    {
        if (is_null($keyName)) {
            $keyName = $this->getKeyName();
        }

        if (isset($this->original[$keyName])) {
            return $this->original[$keyName];
        }

        return $this->getAttribute($keyName);
    }

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
        return $query->where('inoutmode', 1);
    }

    public function scopeCheckOut($query)
    {
        return $query->where('inoutmode', 2);
    }

    public function scopeByDate($query, $date)
    {
        return $query->whereDate('scan_date', $date);
    }

    public function scopeByPin($query, $pin)
    {
        return $query->where('pin', $pin);
    }

    public function scopeBySN($query, $sn)
    {
        return $query->where('sn', $sn);
    }

    // Helper methods
    public function isCheckIn()
    {
        return $this->inoutmode === 1;
    }

    public function isCheckOut()
    {
        return $this->inoutmode === 2;
    }

    public function getVerifyMethodAttribute()
    {
        return $this->verifymode === 1 ? 'Fingerprint' : 'Password';
    }

    public function markAsProcessed()
    {
        $this->update([
            'is_processed' => true,
            'processed_at' => now()
        ]);
    }

    /**
     * Find by composite key
     */
    public static function findByCompositeKey($sn, $scan_date, $pin)
    {
        return static::where('sn', $sn)
                    ->where('scan_date', $scan_date)
                    ->where('pin', $pin)
                    ->first();
    }

    /**
     * Create or find by composite key
     */
    public static function findOrCreateByCompositeKey($sn, $scan_date, $pin, $attributes = [])
    {
        $instance = static::findByCompositeKey($sn, $scan_date, $pin);
        
        if (!$instance) {
            $attributes = array_merge([
                'sn' => $sn,
                'scan_date' => $scan_date,
                'pin' => $pin
            ], $attributes);
            
            $instance = static::create($attributes);
        }
        
        return $instance;
    }
}