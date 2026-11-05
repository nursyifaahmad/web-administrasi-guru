<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Jadwal Pelajaran Resmi SMPN 2 Lemahabang — {{ $academicYear->full_label }}</title>
    <style>
        body { font-family: 'Times New Roman', serif; font-size: 8pt; margin: 10mm; color: #000; }
        .kop-table { width: 100%; border-bottom: 3px double #000; padding-bottom: 6px; margin-bottom: 12px; }
        .kop-table td { text-align: center; }
        .kop-gov { font-size: 10pt; font-weight: bold; }
        .kop-dept { font-size: 9pt; font-weight: bold; }
        .kop-school { font-size: 14pt; font-weight: bold; letter-spacing: 0.5px; }
        .kop-addr { font-size: 8pt; }
        .doc-title { text-align: center; font-size: 11pt; font-weight: bold; margin-bottom: 4px; text-transform: uppercase; }
        .doc-sub { text-align: center; font-size: 9pt; font-weight: bold; margin-bottom: 12px; }
        table.matrix { width: 100%; border-collapse: collapse; text-align: center; margin-bottom: 12px; font-size: 7.5pt; }
        table.matrix th, table.matrix td { border: 1px solid #444; padding: 3px 2px; }
        table.matrix th { background-color: #f0f0f0; }
        .activity-row { background-color: #fff8e1; font-weight: bold; }
        .conflict-cell { background-color: #ffcdd2; font-weight: bold; }
        .sig-table { width: 100%; margin-top: 18px; border: none; }
        .sig-table td { border: none; text-align: center; font-size: 8.5pt; }
        @media print {
            @page { size: landscape; margin: 8mm; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <div class="no-print" style="margin-bottom: 15px; text-align: right;">
        <button onclick="window.print()" style="padding: 6px 14px; background: #059669; color: #fff; border: none; border-radius: 6px; font-weight: bold; cursor: pointer;">
            🖨️ Cetak Dokumen / Simpan PDF
        </button>
    </div>

    {{-- Kop Sekolah --}}
    <table class="kop-table">
        <tr>
            <td style="width: 80px;">
                @php $appLogo = $school?->resolvedAppLogo(); @endphp
                @if(!empty($appLogo['data_uri']))
                    <img src="{{ $appLogo['data_uri'] }}" style="width: 70px; height: auto;" alt="Logo">
                @endif
            </td>
            <td>
                <div class="kop-gov">{{ $school->gov_header }}</div>
                <div class="kop-dept">{{ $school->department_header }}</div>
                <div class="kop-school">{{ $school->school_name }}</div>
                <div class="kop-addr">{{ $school->address }} &bull; Telp: {{ $school->phone ?: '-' }} &bull; Website: {{ $school->website ?: '-' }}</div>
            </td>
            <td style="width: 80px;"></td>
        </tr>
    </table>

    <div class="doc-title">Jadwal Pelajaran Sekolah (21 Rombel &bull; 2 Shift)</div>
    <div class="doc-sub">Tahun Ajaran {{ $academicYear->full_label }} &bull; SMP Negeri 2 Lemahabang</div>

    {{-- Render Jadwal per Hari --}}
    @foreach($matrixData['matrix_by_day'] as $day)
        <div style="font-weight: bold; font-size: 9pt; margin-top: 10px; margin-bottom: 4px; text-transform: uppercase; background: #e0f2fe; padding: 2px 6px;">
            HARI {{ $day['day_name'] }}
        </div>

        {{-- Shift Pagi --}}
        <div style="font-weight: bold; font-size: 8pt; margin-top: 4px; margin-bottom: 2px;">
            Shift Pagi (Kelas VII & IX) — 07.00 s/d 11.55 WIB
        </div>
        <table class="matrix">
            <thead>
                <tr>
                    <th style="width: 30px;">Jam</th>
                    <th style="width: 80px;">Waktu</th>
                    @foreach(['7A','7B','7C','7D','7E','7F','7G','9A','9B','9C','9D','9E','9F','9G'] as $c)
                        <th>{{ $c }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach($day['pagi_slots'] as $slot)
                    @if($slot['is_activity'])
                        <tr class="activity-row">
                            <td>{{ $slot['period_order'] ?: '-' }}</td>
                            <td>{{ $slot['time_range'] }}</td>
                            <td colspan="14">{{ $slot['activity_name'] }}</td>
                        </tr>
                    @else
                        <tr>
                            <td>{{ $slot['period_order'] }}</td>
                            <td>{{ $slot['time_range'] }}</td>
                            @foreach(['7A','7B','7C','7D','7E','7F','7G','9A','9B','9C','9D','9E','9F','9G'] as $c)
                                @php $item = $slot['classes'][$c] ?? null; @endphp
                                <td class="{{ $item?->is_conflict ? 'conflict-cell' : '' }}">
                                    {{ $item?->teacher_code ?: '-' }}
                                </td>
                            @endforeach
                        </tr>
                    @endif
                @endforeach
            </tbody>
        </table>

        {{-- Shift Siang --}}
        @if(count($day['siang_slots']) > 0)
            <div style="font-weight: bold; font-size: 8pt; margin-top: 4px; margin-bottom: 2px;">
                Shift Siang (Kelas VIII) — 12.00 s/d 16.25 WIB
            </div>
            <table class="matrix">
                <thead>
                    <tr>
                        <th style="width: 30px;">Jam</th>
                        <th style="width: 80px;">Waktu</th>
                        @foreach(['8A','8B','8C','8D','8E','8F','8G'] as $c)
                            <th>{{ $c }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($day['siang_slots'] as $slot)
                        @if($slot['is_activity'])
                            <tr class="activity-row">
                                <td>{{ $slot['period_order'] ?: '-' }}</td>
                                <td>{{ $slot['time_range'] }}</td>
                                <td colspan="7">{{ $slot['activity_name'] }}</td>
                            </tr>
                        @else
                            <tr>
                                <td>{{ $slot['period_order'] }}</td>
                                <td>{{ $slot['time_range'] }}</td>
                                @foreach(['8A','8B','8C','8D','8E','8F','8G'] as $c)
                                    @php $item = $slot['classes'][$c] ?? null; @endphp
                                    <td class="{{ $item?->is_conflict ? 'conflict-cell' : '' }}">
                                        {{ $item?->teacher_code ?: '-' }}
                                    </td>
                                @endforeach
                            </tr>
                        @endif
                    @endforeach
                </tbody>
            </table>
        @endif
    @endforeach

    {{-- Titimangsa & Tanda Tangan --}}
    <table class="sig-table">
        <tr>
            <td style="width: 50%;">
                Mengetahui,<br>
                Kepala Sekolah
                <div style="height: 50px;"></div>
                <strong>{{ $school->headmaster_name }}</strong><br>
                NIP. {{ $school->headmaster_nip }}
            </td>
            <td style="width: 50%;">
                {{ $school->default_city ?: 'Lemahabang' }}, {{ \App\Support\IndonesianDate::formatLong(now()) }}<br>
                Wakasek Kurikulum
                <div style="height: 50px;"></div>
                <strong>Drs. Hendra Wijaya, M.M.Pd.</strong><br>
                NIP. 19800315 200604 1 004
            </td>
        </tr>
    </table>
</body>
</html>
