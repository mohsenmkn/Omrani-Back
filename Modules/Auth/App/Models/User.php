<?php

namespace Modules\Auth\App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\HasApiTokens;
use Modules\Document\App\Models\Document;
use Modules\PettyCash\App\Models\PettyCash;
use Modules\Project\App\Models\Project;
use Modules\WBS\App\Models\Task;
use Morilog\Jalali\Jalalian;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
//    protected $fillable = [
//        'name',
//        'mobile',
//        'email',
//        'password',
//        'national_code',
//        'personnel_code',
//    ];

    protected $fillable = [
        'name',
        'mobile',
        'email',
        'password',
        'national_code',
        'personnel_code',
        'otp_code',
        'otp_expires_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
//    protected $hidden = [
//        'password',
//        'remember_token',
//    ];

    protected $hidden = [
        'password',
        'remember_token',
        'otp_code',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
//    protected $casts = [
//        'email_verified_at' => 'datetime',
//    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'otp_expires_at' => 'datetime',
    ];



    public function managedProjects()
    {
        return $this->hasMany(Project::class, 'manager_id');
    }

    public function assignedTasks()
    {
        return $this->hasMany(Task::class, 'assigned_to');
    }

    public function taskAssignments()
    {
        return $this->belongsToMany(Task::class, 'task_assignees')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function pettyCashCustodies()
    {
        return $this->hasMany(PettyCash::class, 'custodian_id');
    }

    public function uploadedDocuments()
    {
        return $this->hasMany(Document::class, 'uploaded_by');
    }

    /**
     * دریافت اطلاعات کارمند از گستراب با استفاده از کد پرسنلی
     */
    public function getGtarabarEmployee()
    {
        if (empty($this->personnel_code)) {
            return null;
        }

        return DB::connection('gtarabar')
            ->table('HCM3.Employee AS e')
            ->join('GNR3.Party AS p', 'p.PartyID', '=', 'e.PartyRef')
            ->where('e.Code', $this->personnel_code)
            ->select(
                'e.EmployeeID',
                'e.Code AS PersonnelCode',
                'e.EmploymentNumber',
                'p.FullName',
                'p.NationalID',
                'p.Mobile',
                'p.Email'
            )
            ->first();
    }

    /**
     * بررسی اینکه آیا کاربر کارمند گستراب هست یا نه
     */
    public function isGtarabarEmployee(): bool
    {
        return !empty($this->personnel_code) && $this->getGtarabarEmployee() !== null;
    }

    /**
     * دریافت لیست ماه‌های موجود برای فیش حقوقی
     */
    public function getAvailablePayslipMonths(): array
    {
        $employee = $this->getGtarabarEmployee();
        if (!$employee) {
            return [];
        }

        return DB::connection('gtarabar')
            ->table('HCM3.PayCalc')
            ->where('EmployeeRef', $employee->EmployeeID)
            ->select('IssueYearMonth')
            ->distinct()
            ->orderBy('IssueYearMonth', 'desc')
            ->pluck('IssueYearMonth')
            ->toArray();
    }

    /**
     * دریافت فیش حقوقی یک ماه خاص
     */
    public function getPayslip(int $yearMonth): array
    {
        $employee = $this->getGtarabarEmployee();
        if (!$employee) {
            return [];
        }

        return DB::connection('gtarabar')->select("
            SELECT
            E.Code AS PersonnelCode,
            P.FullName AS EmployeeName,
            pc.IssueYearMonth,
            CF.Title AS ItemTitle,
            CF.Name AS ItemName,
            CASE
                WHEN CF.Name IN ('UF461', 'UF462', 'UF454') THEN pci.Value / 60.0
                ELSE pci.Value
            END AS Amount,
            CASE
                WHEN CF.Name IN ('NetPay', 'BonusSum', 'DeductSum', 'Effectwork') THEN 'جمع‌ها'
                WHEN CF.Name IN ('UF461', 'UF462', 'UF454') THEN 'کارکرد'
                WHEN CF.Name IN ('EmployeeMainInsurance', 'Tax', 'kasremoaveghe', 'LoanSum', 'TaxGov') THEN 'کسورات'
                ELSE 'مزایا'
            END AS Category
            FROM HCM3.PayCalc pc
            INNER JOIN HCM3.PayCalcItem pci ON pc.PayCalcID = pci.PayCalcRef
            INNER JOIN HCM3.CompensationFactor CF ON pci.CompensationFactorRef = CF.CompensationFactorID
            INNER JOIN HCM3.Employee E ON pc.EmployeeRef = E.EmployeeID
            INNER JOIN GNR3.Party P ON P.PartyID = E.PartyRef
            WHERE pc.IssueYearMonth = ?
            AND E.Code = ?
            AND pci.Value <> 0
            AND CF.Name IN (
                'UF468', 'mozdsanavat', 'Housepay', 'bonkargari', 'ChildPay', 'UF534',
                'ManagePAy', 'sakhtikar', 'UF520', 'UF533', 'ExtraworkPay', 'tatilkari',
                'beinerahi', 'ayabzahab', 'UF541', 'EmployeeMainInsurance', 'Tax',
                'kasremoaveghe', 'LoanSum', 'BonusSum', 'DeductSum', 'NetPay',
                'Effectwork', 'UF461', 'UF462', 'UF454'
            )
            ORDER BY
            CASE CF.Name
                WHEN 'Effectwork' THEN 1 WHEN 'UF461' THEN 2
                WHEN 'UF462' THEN 3 WHEN 'UF454' THEN 4
                WHEN 'UF468' THEN 10 WHEN 'mozdsanavat' THEN 11
                WHEN 'Housepay' THEN 12 WHEN 'bonkargari' THEN 13
                WHEN 'ChildPay' THEN 14 WHEN 'UF534' THEN 15
                WHEN 'ManagePAy' THEN 16 WHEN 'sakhtikar' THEN 17
                WHEN 'UF520' THEN 18 WHEN 'UF533' THEN 19
                WHEN 'ExtraworkPay' THEN 20 WHEN 'tatilkari' THEN 21
                WHEN 'beinerahi' THEN 22 WHEN 'ayabzahab' THEN 23
                WHEN 'UF541' THEN 24 WHEN 'BonusSum' THEN 30
                WHEN 'EmployeeMainInsurance' THEN 40 WHEN 'Tax' THEN 41
                WHEN 'kasremoaveghe' THEN 42 WHEN 'LoanSum' THEN 43
                WHEN 'TaxGov' THEN 44 WHEN 'DeductSum' THEN 50
                WHEN 'NetPay' THEN 60 ELSE 99
            END
        ", [$yearMonth, $this->personnel_code]);
    }

    /**
     * دریافت اطلاعات خلاصه فیش (فقط جمع‌ها)
     */
    public function getPayslipSummary(int $yearMonth): ?object
    {
        $employee = $this->getGtarabarEmployee();
        if (!$employee) {
            return null;
        }

        return DB::connection('gtarabar')->selectOne("
            SELECT
            E.Code AS PersonnelCode,
            P.FullName AS EmployeeName,
                      P.NationalID,
            pc.IssueYearMonth,
            SUM(CASE WHEN CF.Name = 'BonusSum' THEN pci.Value ELSE 0 END) AS TotalBenefits,
            SUM(CASE WHEN CF.Name = 'DeductSum' THEN pci.Value ELSE 0 END) AS TotalDeductions,
            SUM(CASE WHEN CF.Name = 'NetPay' THEN pci.Value ELSE 0 END) AS NetPay
            FROM HCM3.PayCalc pc
            INNER JOIN HCM3.PayCalcItem pci ON pc.PayCalcID = pci.PayCalcRef
            INNER JOIN HCM3.CompensationFactor CF ON pci.CompensationFactorRef = CF.CompensationFactorID
            INNER JOIN HCM3.Employee E ON pc.EmployeeRef = E.EmployeeID
            INNER JOIN GNR3.Party P ON P.PartyID = E.PartyRef
            WHERE pc.IssueYearMonth = ?
            AND E.Code = ?
            AND CF.Name IN ('BonusSum', 'DeductSum', 'NetPay')
            GROUP BY E.Code, P.FullName, P.NationalID, pc.IssueYearMonth
        ", [$yearMonth, $this->personnel_code]);
    }

    /**
     * دریافت آخرین حکم کارگزینی (سمت و شغل) از گستراب
     */
    /**
     * دریافت آخرین حکم کارگزینی (سمت، شغل و واحد) از گستراب
     */
    public function getEmployeeStatute(): ?object
    {
        $employee = $this->getGtarabarEmployee();
        if (!$employee) {
            return null;
        }

        try {
            return DB::connection('gtarabar')->selectOne("
            SELECT TOP 1
                es.EmployeeStatuteID,
                es.EmployeeRef,
                es.PostRef,
                p.Code AS PostCode,
                p.Title AS PostTitle,
                es.JobRef,
                j.Code AS JobCode,
                j.Title AS JobTitle,
                es.DepartmentRef,                          -- ✅ اضافه شد
                es.OrganizationalStructureRef
            FROM HCM3.EmployeeStatute es
            LEFT JOIN HCM3.Post p ON p.PostID = es.PostRef
            LEFT JOIN HCM3.Job j ON j.JobID = es.JobRef
            INNER JOIN HCM3.Employee e ON e.EmployeeID = es.EmployeeRef
            WHERE e.Code = ?
            ORDER BY es.EmployeeStatuteID DESC
        ", [$this->personnel_code]);
        } catch (\Exception $e) {
            Log::error('Error fetching Employee Statute: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * دریافت اطلاعات کامل پرسنل برای داشبورد
     */
    public function getDashboardProfile(): array
    {
        $employee = $this->getGtarabarEmployee();
        $statute = $this->getEmployeeStatute();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'mobile' => $this->mobile,
            'email' => $this->email,
            'personnel_code' => $this->personnel_code,
            'national_code' => $this->national_code,
            'employee_id' => $employee?->EmployeeID,
            'employment_number' => $employee?->EmploymentNumber,
            'post' => [
                'code' => $statute?->PostCode,
                'title' => $statute?->PostTitle,
            ],
            'job' => [
                'code' => $statute?->JobCode,
                'title' => $statute?->JobTitle,
            ],
        ];
    }





    /**
     * مانده انتقالی از پایان سال قبل
     */
    public function getLeaveRemainder(?int $beforeJalaliYear = null): array
    {
        $employee = $this->getGtarabarEmployee();
        if (!$employee) {
            return [];
        }

        try {
            $query = DB::connection('gtarabar')
                ->table('HCM3.LeaveRemainder AS lr')
                ->join('HCM3.LeaveType AS lt', 'lt.LeaveTypeID', '=', 'lr.LeaveTypeRef')
                ->where('lr.EmployeeRef', $employee->EmployeeID)
                ->where('lr.LeaveTypeRef', 1)
                ->where('lr.IsInactive', 0);

            // فقط مانده‌های قبل از شروع سال جاری (انتقالی سال قبل)
            if ($beforeJalaliYear) {
                $query->where('lr.EffectiveYearMonth', '<', $beforeJalaliYear * 100 + 1);
            }

            $remainder = $query->select(
                'lt.Title AS LeaveTypeName',
                'lr.EffectiveYearMonth',
                'lr.Value AS RemainderMinutes',
                DB::raw('CAST(lr.Value AS FLOAT) / 440.0 AS RemainderDays'),
                'lr.EffectiveDate'
            )
                ->orderBy('lr.EffectiveYearMonth', 'desc')
                ->first();

            if (!$remainder) {
                return [];
            }

            $year = (int) floor($remainder->EffectiveYearMonth / 100);
            $month = $remainder->EffectiveYearMonth % 100;

            $persianMonths = [
                1 => 'فروردین', 2 => 'اردیبهشت', 3 => 'خرداد', 4 => 'تیر',
                5 => 'مرداد', 6 => 'شهریور', 7 => 'مهر', 8 => 'آبان',
                9 => 'آذر', 10 => 'دی', 11 => 'بهمن', 12 => 'اسفند'
            ];

            return [
                'leave_type' => $remainder->LeaveTypeName,
                'year' => $year,
                'month' => $month,
                'month_name' => $persianMonths[$month] ?? '',
                'remainder_minutes' => (int) $remainder->RemainderMinutes,
                'remainder_days' => round((float) $remainder->RemainderDays, 2),
                'effective_date' => $remainder->EffectiveDate,
            ];
        } catch (\Exception $e) {
            Log::error('Error fetching Leave Remainder: ' . $e->getMessage());
            return [];
        }
    }



    /**
     * تعداد ماه‌های دارای فیش حقوقی در سال شمسی داده‌شده
     */
    public function getProcessedMonthsCount(int $jalaliYear, array $payslipMonths = []): int
    {
        // اگر لیست فیش‌ها از قبل موجود است، بدون کوئری بشمار
        if (!empty($payslipMonths)) {
            return count(array_filter($payslipMonths, fn ($m) => intdiv((int) $m, 100) === $jalaliYear));
        }

        $employee = $this->getGtarabarEmployee();
        if (!$employee) {
            return 0;
        }

        try {
            return (int) DB::connection('gtarabar')
                ->table('HCM3.PayCalc')
                ->where('EmployeeRef', $employee->EmployeeID)
                ->whereBetween('IssueYearMonth', [$jalaliYear * 100 + 1, $jalaliYear * 100 + 12])
                ->distinct()
                ->count('IssueYearMonth');
        } catch (\Exception $e) {
            Log::error('Error fetching processed months: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * دریافت مرخصی‌های استفاده شده در ماه جاری شمسی
     */
    public function getUsedLeavesCurrentMonth(string $startDate, string $endDate): array
    {
        $employee = $this->getGtarabarEmployee();
        if (!$employee) {
            return [];
        }

        try {
            $usedLeaves = DB::connection('gtarabar')
                ->table('HCM3.LeaveRequest AS lr')
                ->join('HCM3.LeaveType AS lt', 'lt.LeaveTypeID', '=', 'lr.LeaveTypeRef')
                ->where('lr.EmployeeRef', $employee->EmployeeID)
                ->where('lr.FromDateTime', '<=', $endDate)
                ->where('lr.ToDateTime', '>=', $startDate) // شرط Overlap برای گرفتن مرخصی‌های بین دو ماه
                ->where('lr.Status', 10)
                ->select(
                    'lr.LeaveRequestID',
                    'lr.RequestNumber',
                    'lt.Title AS LeaveTypeName',
                    'lr.FromDateTime',
                    'lr.ToDateTime',
                    'lr.IsDaily',
                    'lr.Status',
                    'lr.Reason',
                    DB::raw('DATEDIFF(minute, lr.FromDateTime, lr.ToDateTime) AS DurationMinutes')
                )
                ->selectRaw("CASE
                    WHEN lr.IsDaily = 1 THEN DATEDIFF(day,
                        CASE WHEN lr.FromDateTime < ? THEN ? ELSE lr.FromDateTime END,
                        CASE WHEN lr.ToDateTime > ? THEN ? ELSE lr.ToDateTime END
                    ) + 1
                    ELSE CAST(DATEDIFF(minute,
                        CASE WHEN lr.FromDateTime < ? THEN ? ELSE lr.FromDateTime END,
                        CASE WHEN lr.ToDateTime > ? THEN ? ELSE lr.ToDateTime END
                    ) AS FLOAT) / 440.0
                END AS DaysCountInMonth", [
                    $startDate, $startDate, $endDate, $endDate,
                    $startDate, $startDate, $endDate, $endDate
                ])
                ->orderBy('lr.FromDateTime', 'desc')
                ->get()
                ->toArray();

            return $usedLeaves;
        } catch (\Exception $e) {
            Log::error('Error fetching Used Leaves: ' . $e->getMessage());
            return [];
        }
    }


    /**
     * دریافت مجموع روزهای مرخصی استفاده شده در ماه جاری شمسی
     */
    public function getTotalUsedLeavesCurrentMonth(string $startDate, string $endDate): float
    {
        $employee = $this->getGtarabarEmployee();
        if (!$employee) {
            return 0.0;
        }

        try {
            $total = DB::connection('gtarabar')
                ->table('HCM3.LeaveRequest AS lr')
                ->where('lr.EmployeeRef', $employee->EmployeeID)
                ->where('lr.FromDateTime', '<=', $endDate)
                ->where('lr.ToDateTime', '>=', $startDate)
                ->where('lr.Status', 10)
                ->selectRaw("SUM(CASE
                        WHEN lr.IsDaily = 1 THEN DATEDIFF(day,
                            CASE WHEN lr.FromDateTime < ? THEN ? ELSE lr.FromDateTime END,
                            CASE WHEN lr.ToDateTime > ? THEN ? ELSE lr.ToDateTime END
                        ) + 1
                        ELSE CAST(DATEDIFF(minute,
                            CASE WHEN lr.FromDateTime < ? THEN ? ELSE lr.FromDateTime END,
                            CASE WHEN lr.ToDateTime > ? THEN ? ELSE lr.ToDateTime END
                        ) AS FLOAT) / 440.0
                    END) AS TotalDays", [
                    $startDate, $startDate, $endDate, $endDate,
                    $startDate, $startDate, $endDate, $endDate
                ])
                ->value('TotalDays');

            return round((float) ($total ?? 0), 2);
        } catch (\Exception $e) {
            Log::error('Error fetching Total Used Leaves: ' . $e->getMessage());
            return 0.0;
        }
    }

    /**
     * دریافت مجموع روزهای مرخصی استفاده شده در سال جاری شمسی
     */
    public function getTotalUsedLeavesCurrentYear(string $startDate, string $endDate): float
    {
        $employee = $this->getGtarabarEmployee();
        if (!$employee) {
            return 0.0;
        }

        try {
            $total = DB::connection('gtarabar')
                ->table('HCM3.LeaveRequest AS lr')
                ->where('lr.EmployeeRef', $employee->EmployeeID)
                ->where('lr.FromDateTime', '<=', $endDate)
                ->where('lr.ToDateTime', '>=', $startDate)
                ->where('lr.Status', 10)
                ->selectRaw("SUM(CASE
                        WHEN lr.IsDaily = 1 THEN DATEDIFF(day,
                            CASE WHEN lr.FromDateTime < ? THEN ? ELSE lr.FromDateTime END,
                            CASE WHEN lr.ToDateTime > ? THEN ? ELSE lr.ToDateTime END
                        ) + 1
                        ELSE CAST(DATEDIFF(minute,
                            CASE WHEN lr.FromDateTime < ? THEN ? ELSE lr.FromDateTime END,
                            CASE WHEN lr.ToDateTime > ? THEN ? ELSE lr.ToDateTime END
                        ) AS FLOAT) / 440.0
                    END) AS TotalDays", [
                    $startDate, $startDate, $endDate, $endDate,
                    $startDate, $startDate, $endDate, $endDate
                ])
                ->value('TotalDays');

            return round((float) ($total ?? 0), 2);
        } catch (\Exception $e) {
            Log::error('Error fetching Total Used Leaves Year: ' . $e->getMessage());
            return 0.0;
        }
    }

    /**
     * اطلاعات کامل مرخصی برای داشبورد — نسخه نهایی
     *
     * @param int|null $latestPayslipYearMonth آخرین ماه فیش حقوقی (از کنترلر پاس داده می‌شود)
     * @param array $payslipMonths لیست ماه‌های فیش (برای جلوگیری از کوئری تکراری)
     */
    public function getLeaveDashboardData(?int $latestPayslipYearMonth = null, array $payslipMonths = []): array
    {
        $employee = $this->getGtarabarEmployee();
        if (!$employee) {
            return [];
        }

        // ماه ملاک = آخرین فیش حقوقی صادرشده
        $latestPayslipYearMonth = $latestPayslipYearMonth ?? ($payslipMonths[0] ?? null);

        if ($latestPayslipYearMonth) {
            $jalaliYear  = intdiv($latestPayslipYearMonth, 100);
            $jalaliMonth = $latestPayslipYearMonth % 100;
        } else {
            $now = Jalalian::now();
            $jalaliYear  = $now->getYear();
            $jalaliMonth = $now->getMonth();
        }

        $processedMonths = $this->getProcessedMonthsCount($jalaliYear, $payslipMonths);

        // مانده انتقالی از سال قبل
        $carryOver = $this->getLeaveRemainder($jalaliYear);

        // ✅ تبدیل صحیح شمسی به میلادی با toCarbon()
        $monthCarbon    = Jalalian::fromFormat('Y-m-d', sprintf('%04d-%02d-01', $jalaliYear, $jalaliMonth))->toCarbon();
        $monthStartDate = $monthCarbon->copy()->startOfDay()->format('Y-m-d H:i:s');
        $monthEndDate   = $monthCarbon->copy()->endOfMonth()->endOfDay()->format('Y-m-d H:i:s');
        $yearStartDate  = Jalalian::fromFormat('Y-m-d', sprintf('%04d-01-01', $jalaliYear))->toCarbon()->startOfDay()->format('Y-m-d H:i:s');

        $usedLeaves         = $this->getUsedLeavesCurrentMonth($monthStartDate, $monthEndDate);
        $totalUsedThisMonth = $this->getTotalUsedLeavesCurrentMonth($monthStartDate, $monthEndDate);
        $totalUsedThisYear  = $this->getTotalUsedLeavesCurrentYear($yearStartDate, $monthEndDate);

        // هر ماه: 2 روز و 4 ساعت = 1120 دقیقه
        $monthlyEntitlementMinutes = (2 * 440) + (4 * 60);

        $carryOverDays    = $carryOver['remainder_days'] ?? 0;
        $earnedDays       = ($processedMonths * $monthlyEntitlementMinutes) / 440;
        $totalEntitled    = $carryOverDays + $earnedDays;
        $currentRemainder = $totalEntitled - $totalUsedThisYear;

        $persianMonths = [
            1 => 'فروردین', 2 => 'اردیبهشت', 3 => 'خرداد', 4 => 'تیر',
            5 => 'مرداد', 6 => 'شهریور', 7 => 'مهر', 8 => 'آبان',
            9 => 'آذر', 10 => 'دی', 11 => 'بهمن', 12 => 'اسفند'
        ];

        return [
            'remainder' => $carryOver,
            'used_leaves_current_month' => $usedLeaves,
            'summary' => [
                'processed_year'         => $jalaliYear,
                'processed_month'        => $jalaliMonth,
                'processed_month_name'   => $persianMonths[$jalaliMonth] ?? '',
                'processed_months_count' => $processedMonths,
                'carry_over_days'        => round($carryOverDays, 2),
                'earned_days_this_year'  => round($earnedDays, 2),
                'total_entitled_days'    => round($totalEntitled, 2),
                'used_days_this_month'   => round($totalUsedThisMonth, 2),
                'current_remainder_days' => round($currentRemainder, 2),
                'current_remainder_human' => self::formatDaysHuman($currentRemainder),   // ✅
                'used_days_this_year'    => round($totalUsedThisYear, 2),
                'used_this_year_human'   => self::formatDaysHuman($totalUsedThisYear),   // ✅
                'total_entitled_human'   => self::formatDaysHuman($totalEntitled),       // ✅
            ]
        ];
    }


    /**
     * تبدیل روز اعشاری به فرمت انسانی (بر مبنای روز کاری 440 دقیقه)
     */
    public static function formatDaysHuman(float $days): string
    {
        $totalMinutes = (int) round($days * 440);
        $d = intdiv($totalMinutes, 440);
        $rest = $totalMinutes % 440;
        $h = intdiv($rest, 60);
        $m = $rest % 60;

        $parts = [];
        if ($d) $parts[] = $d . ' روز';
        if ($h) $parts[] = $h . ' ساعت';
        if ($m) $parts[] = $m . ' دقیقه';

        return implode(' و ', $parts) ?: '0 روز';
    }















}
