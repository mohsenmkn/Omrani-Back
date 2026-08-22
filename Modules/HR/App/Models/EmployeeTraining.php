<?php


namespace Modules\HR\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Auth\App\Models\User;

class EmployeeTraining extends Model
{
    protected $fillable = [
        'user_id',
        'national_code',
        'external_id',
        'first_name',
        'last_name',
        'deputy',
        'management',
        'post_title',
        'course_code',
        'course_title',
        'session_duration',
        'performance_hours',
        'soap_raw_data',
        'synced_at',
    ];

    protected $casts = [
        'performance_hours' => 'decimal:2',
        'soap_raw_data' => 'array',
        'synced_at' => 'datetime',
    ];

    // ── روابط ──

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ── Accessors ──

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    /**
     * تبدیل مدت جلسه (HH:MM:SS) به تعداد ساعت
     */
    public function getSessionHoursAttribute(): float
    {
        if (empty($this->session_duration)) return 0;

        $parts = explode(':', $this->session_duration);
        if (count($parts) !== 3) return 0;

        [$h, $m, $s] = array_map('intval', $parts);
        return $h + ($m / 60) + ($s / 3600);
    }

    /**
     * وضعیت: بر اساس ساعت عملکرد
     */
    public function getStatusTitleAttribute(): string
    {
        if ($this->performance_hours >= $this->session_hours && $this->session_hours > 0) {
            return 'تکمیل شده';
        }
        if ($this->performance_hours > 0) {
            return 'در حال برگزاری';
        }
        return 'ثبت شده';
    }

    public function getStatusSeverityAttribute(): string
    {
        return match ($this->status_title) {
            'تکمیل شده' => 'success',
            'در حال برگزاری' => 'warning',
            default => 'info',
        };
    }
}
