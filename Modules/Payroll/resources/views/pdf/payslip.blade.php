<!-- Modules/Payroll/resources/views/pdf/payslip.blade.php -->
<!DOCTYPE html>
<html dir="rtl">
<head>
    <meta charset="UTF-8">
    <style>
        * { font-family: 'DejaVu Sans', sans-serif; }
        body { direction: rtl; font-size: 12px; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #333; padding-bottom: 10px; }
        .header h1 { font-size: 18px; margin: 0; }
        .header h2 { font-size: 14px; color: #666; margin: 5px 0 0; }
        .info-table { width: 100%; margin-bottom: 15px; }
        .info-table td { padding: 4px 8px; }
        .info-table .label { font-weight: bold; color: #555; width: 25%; }
        .items-table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
        .items-table th { background: #2c3e50; color: white; padding: 8px; text-align: right; }
        .items-table td { padding: 6px 8px; border-bottom: 1px solid #ddd; }
        .items-table tr:nth-child(even) { background: #f9f9f9; }
        .category-row { background: #ecf0f1 !important; font-weight: bold; }
        .income { color: #27ae60; }
        .deduction { color: #e74c3c; }
        .total { font-weight: bold; font-size: 14px; }
        .summary-box { border: 2px solid #2c3e50; padding: 10px; border-radius: 5px; }
        .net-pay { font-size: 18px; color: #2c3e50; text-align: center; margin-top: 10px; }
    </style>
</head>
<body>
<div class="header">
    <h1>فیش حقوقی</h1>
    <h2>{{ $payslip->yearMonthLabel }}</h2>
</div>

<table class="info-table">
    <tr>
        <td class="label">کد پرسنلی:</td>
        <td>{{ $payslip->personnelCode }}</td>
        <td class="label">نام و نام خانوادگی:</td>
        <td>{{ $payslip->employeeName }}</td>
    </tr>
    <tr>
        <td class="label">کد ملی:</td>
        <td>{{ $payslip->nationalId }}</td>
        <td class="label">نام پدر:</td>
        <td>{{ $payslip->fatherName ?? '-' }}</td>
    </tr>
    <tr>
        <td class="label">روزهای کارکرد:</td>
        <td>{{ $payslip->effectiveDays }} روز</td>
        <td></td>
        <td></td>
    </tr>
</table>

<table class="items-table">
    <thead>
    <tr>
        <th style="width: 60%;">شرح</th>
        <th style="width: 40%; text-align: left;">مبلغ (ریال)</th>
    </tr>
    </thead>
    <tbody>
    @foreach($payslip->getGroupedItems() as $category => $items)
        <tr class="category-row">
            <td colspan="2">{{ $category }}</td>
        </tr>
        @foreach($items as $item)
            <tr>
                <td>{{ $item['title'] }}</td>
                <td style="text-align: left;" class="{{ $category === 'کسورات' ? 'deduction' : 'income' }}">
                    {{ number_format($item['amount']) }}
                </td>
            </tr>
        @endforeach
    @endforeach
    </tbody>
</table>

<div class="summary-box">
    <table style="width: 100%;">
        <tr>
            <td>جمع کل مزایا:</td>
            <td style="text-align: left;" class="income total">
                {{ number_format($payslip->totalBenefits) }} ریال
            </td>
        </tr>
        <tr>
            <td>جمع کل کسورات:</td>
            <td style="text-align: left;" class="deduction total">
                {{ number_format($payslip->totalDeductions) }} ریال
            </td>
        </tr>
    </table>
    <div class="net-pay">
        خالص پرداختی: {{ number_format($payslip->netPay) }} ریال
    </div>
</div>
</body>
</html>
