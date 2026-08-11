<?php

// Modules/Attendance/App/DTOs/AttendanceSummaryDTO.php

namespace Modules\Attendance\App\DTOs;

class AttendanceSummaryDTO
{
    public function __construct(
        public readonly string $personnelCode,
        public readonly string $employeeName,
        public readonly string $monthTitle,
        public readonly string $monthKey,

        // آمار روزها
        public readonly int $totalDays,
        public readonly int $workDays,
        public readonly int $presenceDays,
        public readonly int $absenceDays,
        public readonly int $leaveDays,
        public readonly int $missionDays,
        public readonly int $holidayDays,
        public readonly int $restDays,

        // زمان‌ها (به دقیقه)
        public readonly int $totalOvertimeMinutes,
        public readonly int $totalShortageMinutes,
        public readonly int $totalLateMinutes,
        public readonly int $totalEarlyLeaveMinutes,
        public readonly int $totalLeaveMinutes,
        public readonly int $totalMissionMinutes,

        // درصد حضور
        public readonly float $attendanceRate
    ) {}

    /**
     * تبدیل به آرایه برای JSON
     */
    public function toArray(): array
    {
        return [
            'personnel_code' => $this->personnelCode,
            'employee_name' => $this->employeeName,
            'month_title' => $this->monthTitle,
            'month_key' => $this->monthKey,

            'days' => [
                'total' => $this->totalDays,
                'work' => $this->workDays,
                'presence' => $this->presenceDays,
                'absence' => $this->absenceDays,
                'leave' => $this->leaveDays,
                'mission' => $this->missionDays,
                'holiday' => $this->holidayDays,
                'rest' => $this->restDays,
            ],

            'time' => [
                'overtime' => $this->totalOvertimeMinutes,
                'overtime_formatted' => $this->formatMinutes($this->totalOvertimeMinutes),
                'shortage' => $this->totalShortageMinutes,
                'shortage_formatted' => $this->formatMinutes($this->totalShortageMinutes),
                'late' => $this->totalLateMinutes,
                'late_formatted' => $this->formatMinutes($this->totalLateMinutes),
                'early_leave' => $this->totalEarlyLeaveMinutes,
                'early_leave_formatted' => $this->formatMinutes($this->totalEarlyLeaveMinutes),
                'leave' => $this->totalLeaveMinutes,
                'leave_formatted' => $this->formatMinutes($this->totalLeaveMinutes),
                'mission' => $this->totalMissionMinutes,
                'mission_formatted' => $this->formatMinutes($this->totalMissionMinutes),
            ],

            'attendance_rate' => round($this->attendanceRate, 1),
        ];
    }

    private function formatMinutes(int $minutes): string
    {
        $hours = intdiv($minutes, 60);
        $mins = $minutes % 60;
        return sprintf('%02d:%02d', $hours, $mins);
    }
}
