<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 12mm; }
        body { color: #1e293b; font-family: DejaVu Sans, sans-serif; font-size: 9px; }
        .page { page-break-after: always; }
        .page:last-child { page-break-after: auto; }
        .page-heading { border-bottom: 1px solid #cbd5e1; margin-bottom: 7mm; padding-bottom: 3mm; }
        .page-heading table, .slip table { border-collapse: collapse; width: 100%; }
        .page-heading-title { color: #0f172a; font-size: 15px; font-weight: bold; }
        .muted { color: #64748b; }
        .slip-grid { border-collapse: separate; border-spacing: 4mm; table-layout: fixed; width: 100%; }
        .slip-cell { padding: 0; vertical-align: top; width: 50%; }
        .slip { border: 1px solid #94a3b8; height: 112mm; padding: 4mm; }
        .slip-header { border-bottom: 1px solid #cbd5e1; margin-bottom: 2mm; padding-bottom: 2mm; }
        .slip-title { color: #0f172a; font-size: 11px; font-weight: bold; }
        .slip-period { color: #4338ca; font-weight: bold; text-align: right; }
        .identity { margin-bottom: 2mm; }
        .identity td { padding: 1px 0; vertical-align: top; }
        .label { color: #64748b; width: 27mm; }
        .items th { background: #f1f5f9; color: #475569; font-size: 8px; text-align: left; }
        .items th, .items td { border-bottom: 1px solid #e2e8f0; padding: 3px 2px; }
        .amount { text-align: right; white-space: nowrap; }
        .section-row td { background: #f8fafc; color: #475569; font-size: 8px; font-weight: bold; padding-top: 5px; }
        .total-row td { border-top: 1px solid #64748b; color: #0f172a; font-size: 10px; font-weight: bold; padding-top: 5px; }
        .net-row td { background: #eef2ff; color: #312e81; font-size: 11px; font-weight: bold; padding: 5px 2px; }
        .status { color: #047857; font-size: 8px; font-weight: bold; text-align: right; }
        .signature { color: #64748b; padding-top: 8mm; text-align: right; }
        .signature-line { border-top: 1px solid #94a3b8; display: inline-block; margin-top: 10mm; min-width: 35mm; padding-top: 1mm; }
        .empty-cell { border: 0; }
    </style>
</head>
<body>
@foreach($salaryPages as $rows)
    <div class="page">
        <div class="page-heading">
            <table>
                <tr>
                    @if($organizationSettings->logo_path)
                        <td style="width: 18mm; vertical-align: top;">
                            <img src="{{ $organizationLogoDataUri }}" alt="" style="height: 16mm; max-width: 16mm;">
                        </td>
                    @endif
                    <td>
                        <div class="page-heading-title">SLIP GAJI PEGAWAI</div>
                        <div class="muted">{{ $organizationSettings->foundation_name }} · Periode {{ \Illuminate\Support\Carbon::createFromFormat('Y-m', $period)->locale('id')->translatedFormat('F Y') }}</div>
                        @if($organizationSettings->foundation_address)
                            <div class="muted">{{ $organizationSettings->foundation_address }}</div>
                        @endif
                    </td>
                    <td class="muted" style="text-align:right">
                        {{ $salaryCount }} pegawai<br>
                        Dicetak {{ $generatedAt->format('d/m/Y H:i') }}
                    </td>
                </tr>
            </table>
        </div>

        <table class="slip-grid">
            <tbody><tr>
                    @foreach($rows as $salary)
                        @php
                            $employee = $salary->employee;
                            $branch = $employee?->branch;
                        @endphp
                        <td class="slip-cell">
                            <div class="slip">
                                <table class="slip-header">
                                    <tr>
                                        <td class="slip-title">Rincian penghasilan</td>
                                        <td class="slip-period">{{ \Illuminate\Support\Carbon::createFromFormat('Y-m', $salary->period)->locale('id')->translatedFormat('F Y') }}</td>
                                    </tr>
                                    <tr>
                                        <td class="muted">{{ $branch?->name ?? 'Cabang' }}</td>
                                        <td class="status">{{ $salary->status === 'PAID' ? 'SUDAH DIBAYAR' : 'BELUM DIBAYAR' }}</td>
                                    </tr>
                                </table>

                                <table class="identity">
                                    <tr><td class="label">Nama</td><td><strong>{{ $employee?->name ?? 'Pegawai terhapus' }}</strong></td></tr>
                                    <tr><td class="label">NIP</td><td>{{ $employee?->nip ?? '-' }}</td></tr>
                                    <tr><td class="label">Jenis</td><td>{{ $employee?->type === 'GURU' ? 'Guru' : ($employee?->type === 'STAFF' ? 'Staff' : '-') }}</td></tr>
                                </table>

                                <table class="items">
                                    <thead><tr><th>Keterangan</th><th class="amount">Jumlah (Rp)</th></tr></thead>
                                    <tbody>
                                        <tr class="section-row"><td colspan="2">PENGHASILAN</td></tr>
                                        <tr><td>Gaji pokok</td><td class="amount">{{ number_format((float) ($employee?->base_salary ?? 0), 0, ',', '.') }}</td></tr>
                                        @foreach($salary->position_details ?? [] as $positionDetail)
                                            @if((float) ($positionDetail['salary_addition'] ?? 0) > 0)
                                                <tr>
                                                    <td>Tambahan gaji · {{ $positionDetail['name'] ?? 'Jabatan' }}@if($positionDetail['class_name'] ?? null) · {{ $positionDetail['class_name'] }}@endif</td>
                                                    <td class="amount">{{ number_format((float) $positionDetail['salary_addition'], 0, ',', '.') }}</td>
                                                </tr>
                                            @endif
                                        @endforeach
                                        @forelse($salary->incentive_details ?? [] as $detail)
                                            <tr><td>Insentif · {{ $detail['name'] ?? 'Insentif' }}</td><td class="amount">{{ number_format((float) ($detail['amount'] ?? 0), 0, ',', '.') }}</td></tr>
                                        @empty
                                            @if((float) $salary->incentives > 0)
                                                <tr><td>Total insentif</td><td class="amount">{{ number_format((float) $salary->incentives, 0, ',', '.') }}</td></tr>
                                            @endif
                                        @endforelse
                                        @forelse($salary->allowance_details ?? [] as $detail)
                                            @if((float) ($detail['amount'] ?? 0) > 0)
                                                <tr>
                                                    <td>
                                                        {{ $detail['name'] ?? 'Tunjangan' }}
                                                        @if(isset($detail['count']))
                                                            <span class="muted">({{ $detail['count'] }} jam × {{ number_format((float) ($detail['rate'] ?? 0), 0, ',', '.') }})</span>
                                                        @endif
                                                    </td>
                                                    <td class="amount">{{ number_format((float) $detail['amount'], 0, ',', '.') }}</td>
                                                </tr>
                                            @endif
                                        @empty
                                            @if((float) $salary->allowances > 0)
                                                <tr><td>Tunjangan</td><td class="amount">{{ number_format((float) $salary->allowances, 0, ',', '.') }}</td></tr>
                                            @endif
                                        @endforelse
                                        <tr class="total-row"><td>Total penghasilan</td><td class="amount">{{ number_format((float) ($employee?->base_salary ?? 0) + (float) $salary->position_salary + (float) $salary->incentives + (float) $salary->allowances, 0, ',', '.') }}</td></tr>
                                        <tr class="section-row"><td colspan="2">POTONGAN</td></tr>
                                        <tr><td>Potongan absensi/lainnya</td><td class="amount">{{ number_format((float) $salary->deductions, 0, ',', '.') }}</td></tr>
                                        <tr class="net-row"><td>GAJI BERSIH</td><td class="amount">{{ number_format((float) $salary->net_salary, 0, ',', '.') }}</td></tr>
                                    </tbody>
                                </table>

                                <div class="signature">
                                    <div>{{ $branch?->name ?? 'Yayasan' }}, {{ $generatedAt->locale('id')->translatedFormat('d F Y') }}</div>
                                    <div class="signature-line">Penerima</div>
                                </div>
                            </div>
                        </td>
                    @endforeach
                    @if($rows->count() < 2)<td class="slip-cell empty-cell"></td>@endif
            </tr></tbody>
        </table>
    </div>
@endforeach
</body>
</html>
