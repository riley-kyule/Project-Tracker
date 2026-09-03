<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: sans-serif; font-size: 12px; color: #111827; }
        h1 { font-size: 18px; margin: 0 0 2px; }
        .meta { color: #6b7280; margin-bottom: 14px; font-size: 11px; }
        h2 { font-size: 13px; margin: 16px 0 6px; border-bottom: 1px solid #d1d5db; padding-bottom: 3px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
        td { padding: 4px 6px; border: 1px solid #e5e7eb; }
        td.r { text-align: right; }
        .tot td { font-weight: bold; background: #f3f4f6; }
        .cols { width: 100%; }
        .cols td { border: 0; vertical-align: top; padding: 0 8px 0 0; }
        .net { font-size: 15px; font-weight: bold; margin-top: 10px; }
        .muted { color: #6b7280; }
        .foot { margin-top: 20px; font-size: 10px; color: #6b7280; }
    </style>
</head>
@php $c = $payslip->currency; $money = fn ($v) => $c . ' ' . number_format((float) $v, 2); @endphp
<body>
    <table class="cols" style="margin-bottom:6px">
        <tr>
            @if ($logoData)
                <td style="width:70px"><img src="{{ $logoData }}" style="max-height:56px;max-width:64px"></td>
            @endif
            <td>
                <h1 style="margin:0">{{ $companyName }}</h1>
                @if ($company->payslip_company_address)
                    <span class="muted" style="white-space:pre-line">{{ $company->payslip_company_address }}</span>
                @endif
            </td>
            <td style="text-align:right;vertical-align:top">
                <strong>PAYSLIP</strong><br>
                <span class="muted">
                    {{ $period->label }}<br>
                    Pay date {{ $period->pay_date->format('d/M/Y') }}
                    @if ($company->company_kra_pin)<br>Employer KRA PIN {{ $company->company_kra_pin }}@endif
                </span>
            </td>
        </tr>
    </table>
    <hr style="border:0;border-top:1px solid #d1d5db;margin:0 0 12px">


    <table class="cols">
        <tr>
            <td style="width:50%">
                <strong>{{ $employee->full_name }}</strong><br>
                <span class="muted">
                    {{ $employee->job_title }}<br>
                    {{ $employee->department?->name }}<br>
                    Staff #{{ $employee->staff_number }}
                </span>
            </td>
            <td style="width:50%">
                <span class="muted">
                    KRA PIN: {{ $employee->kra_pin ?: '—' }}<br>
                    NSSF: {{ $employee->nssf_number ?: '—' }}<br>
                    SHA/SHIF: {{ $employee->shif_number ?: '—' }}<br>
                    Bank: {{ $employee->bank_name ?: '—' }} {{ $employee->bank_account_number }}
                </span>
            </td>
        </tr>
    </table>

    <h2>Earnings</h2>
    <table>
        @foreach ($payslip->earnings ?? [] as $line)
            <tr><td>{{ $line['name'] }}</td><td class="r">{{ $money($line['amount']) }}</td></tr>
        @endforeach
        <tr class="tot"><td>Gross pay</td><td class="r">{{ $money($payslip->gross_pay) }}</td></tr>
    </table>

    <h2>Deductions</h2>
    <table>
        <tr><td>PAYE (after relief)</td><td class="r">{{ $money($payslip->paye) }}</td></tr>
        <tr><td class="muted">— tax before relief</td><td class="r muted">{{ $money($payslip->paye_before_relief) }}</td></tr>
        <tr><td class="muted">— personal relief</td><td class="r muted">({{ $money($payslip->personal_relief) }})</td></tr>
        @if ((float) $payslip->insurance_relief > 0)
            <tr><td class="muted">— insurance relief</td><td class="r muted">({{ $money($payslip->insurance_relief) }})</td></tr>
        @endif
        <tr><td>NSSF</td><td class="r">{{ $money($payslip->nssf_employee) }}</td></tr>
        <tr><td>SHIF</td><td class="r">{{ $money($payslip->shif_employee) }}</td></tr>
        <tr><td>Affordable Housing Levy</td><td class="r">{{ $money($payslip->housing_levy_employee) }}</td></tr>
        @foreach ($payslip->pretax_deductions ?? [] as $line)
            <tr><td>{{ $line['name'] }}</td><td class="r">{{ $money($line['amount']) }}</td></tr>
        @endforeach
        @foreach ($payslip->other_deductions ?? [] as $line)
            <tr><td>{{ $line['name'] }}</td><td class="r">{{ $money($line['amount']) }}</td></tr>
        @endforeach
        <tr class="tot"><td>Total deductions</td><td class="r">{{ $money($payslip->total_deductions) }}</td></tr>
    </table>

    <div class="net">Net pay: {{ $money($payslip->net_pay) }}</div>

    <h2>Employer contributions</h2>
    <table>
        <tr><td>NSSF (employer)</td><td class="r">{{ $money($payslip->nssf_employer) }}</td></tr>
        <tr><td>Housing Levy (employer)</td><td class="r">{{ $money($payslip->housing_levy_employer) }}</td></tr>
        @if ((float) $payslip->nita_employer > 0)
            <tr><td>NITA levy</td><td class="r">{{ $money($payslip->nita_employer) }}</td></tr>
        @endif
        <tr class="tot"><td>Total employer cost</td><td class="r">{{ $money($payslip->employer_cost) }}</td></tr>
    </table>

    @if ($payslip->ytd)
        <h2>Year to date</h2>
        <table>
            <tr><td>Gross</td><td class="r">{{ $money($payslip->ytd['gross'] ?? 0) }}</td></tr>
            <tr><td>PAYE</td><td class="r">{{ $money($payslip->ytd['paye'] ?? 0) }}</td></tr>
            <tr><td>NSSF</td><td class="r">{{ $money($payslip->ytd['nssf'] ?? 0) }}</td></tr>
            <tr><td>SHIF</td><td class="r">{{ $money($payslip->ytd['shif'] ?? 0) }}</td></tr>
            <tr><td>Housing Levy</td><td class="r">{{ $money($payslip->ytd['housing_levy'] ?? 0) }}</td></tr>
        </table>
    @endif

    <div class="foot">
        {{ $company->payslip_footer_note ?: 'This is a computer-generated payslip.' }}
    </div>
</body>
</html>
