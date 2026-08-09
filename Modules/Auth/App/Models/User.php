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
//    public function getEmployeeStatute(): ?object
//    {
//        $employee = $this->getGtarabarEmployee();
//        if (!$employee) {
//            return null;
//        }
//
//        try {
//            return DB::connection('gtarabar')->selectOne("
//            SELECT TOP 1
//                es.EmployeeStatuteID,
//                es.EmployeeRef,
//                es.PostRef,
//                p.Code AS PostCode,
//                p.Title AS PostTitle,
//                es.JobRef,
//                j.Code AS JobCode,
//                j.Title AS JobTitle,
//                es.OrganizationalStructureRef
//            FROM HCM3.EmployeeStatute es
//            LEFT JOIN HCM3.Post p ON p.PostID = es.PostRef
//            LEFT JOIN HCM3.Job j ON j.JobID = es.JobRef
//            INNER JOIN HCM3.Employee e ON e.EmployeeID = es.EmployeeRef
//            WHERE e.Code = ?
//            ORDER BY es.EmployeeStatuteID DESC
//        ", [$this->personnel_code]);
//        } catch (\Exception $e) {
//            Log::error('Error fetching Employee Statute: ' . $e->getMessage());
//            return null;
//        }
//    }

    /**
     * دریافت اطلاعات کامل پرسنل برای داشبورد
     */
//    public function getDashboardProfile(): array
//    {
//        $employee = $this->getGtarabarEmployee();
//        $statute = $this->getEmployeeStatute();
//
//        return [
//            'id' => $this->id,
//            'name' => $this->name,
//            'mobile' => $this->mobile,
//            'email' => $this->email,
//            'personnel_code' => $this->personnel_code,
//            'national_code' => $this->national_code,
//            'employee_id' => $employee?->EmployeeID,
//            'employment_number' => $employee?->EmploymentNumber,
//            'post' => [
//                'code' => $statute?->PostCode,
//                'title' => $statute?->PostTitle,
//            ],
//            'job' => [
//                'code' => $statute?->JobCode,
//                'title' => $statute?->JobTitle,
//            ],
//        ];
//    }





    /**
     * دریافت مانده مرخصی استحقاقی از گستراب
     * Value در جدول LeaveRemainder به دقیقه است (480 دقیقه = 1 روز کاری)
     */
    public function getLeaveRemainder(): array
    {
        $employee = $this->getGtarabarEmployee();
        if (!$employee) {
            return [];
        }

        try {
            // دریافت آخرین مانده مرخصی استحقاقی (LeaveTypeRef = 1)
            $remainder = DB::connection('gtarabar')
                ->table('HCM3.LeaveRemainder AS lr')
                ->join('HCM3.LeaveType AS lt', 'lt.LeaveTypeID', '=', 'lr.LeaveTypeRef')
                ->where('lr.EmployeeRef', $employee->EmployeeID)
                ->where('lr.LeaveTypeRef', 1) // مرخصی استحقاقی
                ->where('lr.IsInactive', 0)
                ->select(
                    'lt.Title AS LeaveTypeName',
                    'lr.EffectiveYearMonth',
                    'lr.Value AS RemainderMinutes',
                    DB::raw('CAST(lr.Value AS FLOAT) / 480.0 AS RemainderDays'),
                    'lr.EffectiveDate'
                )
                ->orderBy('lr.EffectiveYearMonth', 'desc')
                ->first();

            if (!$remainder) {
                return [];
            }

            // استخراج سال و ماه از EffectiveYearMonth (فرمت YYYYMM)
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
     * دریافت مرخصی‌های استفاده شده در ماه جاری شمسی
     */
    public function getUsedLeavesCurrentMonth(): array
    {
        $employee = $this->getGtarabarEmployee();
        if (!$employee) {
            return [];
        }

        try {
            // تاریخ شروع و پایان ماه جاری شمسی (مرداد 1405)
            // مرداد 1405 ≈ 23 جولای 2026 تا 22 آگوست 2026
            $startDate = '2026-07-23 00:00:00';
            $endDate = '2026-08-22 23:59:59';

            $usedLeaves = DB::connection('gtarabar')
                ->table('HCM3.LeaveRequest AS lr')
                ->join('HCM3.LeaveType AS lt', 'lt.LeaveTypeID', '=', 'lr.LeaveTypeRef')
                ->where('lr.EmployeeRef', $employee->EmployeeID)
                ->whereBetween('lr.FromDateTime', [$startDate, $endDate])
                ->where('lr.Status', 10) // 10 = تایید شده
                ->select(
                    'lr.LeaveRequestID',
                    'lr.RequestNumber',
                    'lt.Title AS LeaveTypeName',
                    'lr.FromDateTime',
                    'lr.ToDateTime',
                    'lr.IsDaily',
                    'lr.Status',
                    'lr.Reason',
                    DB::raw('DATEDIFF(minute, lr.FromDateTime, lr.ToDateTime) AS DurationMinutes'),
                    DB::raw('CASE WHEN lr.IsDaily = 1 THEN DATEDIFF(day, lr.FromDateTime, lr.ToDateTime) + 1 ELSE 0 END AS DaysCount')
                )
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
    public function getTotalUsedLeavesCurrentMonth(): int
    {
        $employee = $this->getGtarabarEmployee();
        if (!$employee) {
            return 0;
        }

        try {
            // مرداد 1405
            $startDate = '2026-07-23 00:00:00';
            $endDate = '2026-08-22 23:59:59';

            $total = DB::connection('gtarabar')
                ->table('HCM3.LeaveRequest AS lr')
                ->where('lr.EmployeeRef', $employee->EmployeeID)
                ->whereBetween('lr.FromDateTime', [$startDate, $endDate])
                ->where('lr.Status', 10)
                ->selectRaw('SUM(CASE WHEN lr.IsDaily = 1 THEN DATEDIFF(day, lr.FromDateTime, lr.ToDateTime) + 1 ELSE 0 END) AS TotalDays')
                ->value('TotalDays');

            return (int) ($total ?? 0);
        } catch (\Exception $e) {
            Log::error('Error fetching Total Used Leaves: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * دریافت مجموع روزهای مرخصی استفاده شده در سال جاری شمسی
     */
    public function getTotalUsedLeavesCurrentYear(): int
    {
        $employee = $this->getGtarabarEmployee();
        if (!$employee) {
            return 0;
        }

        try {
            // سال 1405 شمسی: 21 مارس 2026 تا 20 مارس 2027
            $startDate = '2026-03-21 00:00:00';
            $endDate = '2027-03-20 23:59:59';

            $total = DB::connection('gtarabar')
                ->table('HCM3.LeaveRequest AS lr')
                ->where('lr.EmployeeRef', $employee->EmployeeID)
                ->whereBetween('lr.FromDateTime', [$startDate, $endDate])
                ->where('lr.Status', 10)
                ->selectRaw('SUM(CASE WHEN lr.IsDaily = 1 THEN DATEDIFF(day, lr.FromDateTime, lr.ToDateTime) + 1 ELSE 0 END) AS TotalDays')
                ->value('TotalDays');

            return (int) ($total ?? 0);
        } catch (\Exception $e) {
            Log::error('Error fetching Total Used Leaves Year: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * دریافت اطلاعات کامل مرخصی برای داشبورد
     */
    public function getLeaveDashboardData(): array
    {
        $remainder = $this->getLeaveRemainder();
        $usedLeaves = $this->getUsedLeavesCurrentMonth();
        $totalUsedThisMonth = $this->getTotalUsedLeavesCurrentMonth();
        $totalUsedThisYear = $this->getTotalUsedLeavesCurrentYear();

        // محاسبه کل مرخصی استحقاقی (مانده + استفاده شده)
        $totalEntitled = ($remainder['remainder_days'] ?? 0) + $totalUsedThisYear;

        return [
            'remainder' => $remainder,
            'used_leaves_current_month' => $usedLeaves,
            'summary' => [
                'total_remainder_days' => $remainder['remainder_days'] ?? 0,
                'total_entitled_days' => round($totalEntitled, 2),
                'total_used_days_year' => $totalUsedThisYear,
                'used_days_this_month' => $totalUsedThisMonth,
            ]
        ];
    }

    /**
     * به‌روزرسانی متد getDashboardProfile برای شامل شدن اطلاعات مرخصی
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
     * دریافت اطلاعات کامل پروفایل برای داشبورد
     */
    public function getDashboardProfile(): array
    {
        $employee = $this->getGtarabarEmployee();
        $statute = $this->getEmployeeStatute();
        $leaveData = $this->getLeaveDashboardData();

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
            'leave' => $leaveData,
        ];
    }








/*           oooooollllllddddddddddddd
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

    public function getGtarabarEmployee()
    {
        if (empty($this->personnel_code)) {
            return null;
        }

        return DB::connection('gtarabar')
            ->table('HCM3.Employee AS e')
            ->join('GNR3.Party AS p', 'p.PartyID', '=', 'e.PartyRef')
            ->where('e.Code', $this->personnel_code)  // 🔑 جستجو بر اساس کد پرسنلی
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

    public function isGtarabarEmployee(): bool
    {
        return !empty($this->personnel_code) && $this->getGtarabarEmployee() !== null;
    }


    /**
     * دریافت لیست ماه‌های موجود برای فیش حقوقی

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
     *
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
*/







}
