<?php

namespace Modules\Auth\App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
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
