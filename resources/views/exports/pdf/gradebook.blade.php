<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Daftar Nilai - {{ $teachingClass->class_name }}</title>
    @include('exports.pdf.partials.styles')
</head>
<body>
    @php
        $phCols = $gradebook['ph_columns'];
        $ntCols = $gradebook['nt_columns'];
        $w = $gradebook['weight'];
        $precision = (int) $w->rounding_precision;
    @endphp

    @include('exports.pdf.partials.header', [
        'documentTitle' => 'DAFTAR NILAI & CAPAIAN KOMPETENSI PESERTA DIDIK',
        'documentSubtitle' => sprintf(
            'Rumus Kontrak Belajar NA: Rata-rata PH (%s%%) + Kehadiran (%s%%) + Rata-rata NT (%s%%) + STS (%s%%) + SAS (%s%%) = 100%%',
            rtrim(rtrim(number_format($w->weight_ph, 2), '0'), '.'),
            rtrim(rtrim(number_format($w->weight_attendance, 2), '0'), '.'),
            rtrim(rtrim(number_format($w->weight_nt, 2), '0'), '.'),
            rtrim(rtrim(number_format($w->weight_sts, 2), '0'), '.'),
            rtrim(rtrim(number_format($w->weight_sas, 2), '0'), '.')
        ),
    ])

    <table class="data-table compact-matrix">
        <thead>
            <tr>
                <th rowspan="2" style="width: 24px;">No</th>
                <th rowspan="2" style="width: 165px;">Nama Lengkap</th>
                <th rowspan="2" style="width: 28px;">L/P</th>
                @if($phCols->count() > 0)
                    <th colspan="{{ $phCols->count() }}">Nilai Harian / Keaktifan / Karakter</th>
                @endif
                <th rowspan="2" style="width: 44px;">Nilai Kehadiran</th>
                @if($ntCols->count() > 0)
                    <th colspan="{{ $ntCols->count() }}">Nilai Tugas</th>
                @endif
                <th rowspan="2" style="width: 38px;">Nilai STS</th>
                <th rowspan="2" style="width: 38px;">Nilai SAS</th>
                <th rowspan="2" style="width: 44px;">NA</th>
                <th rowspan="2" style="width: 66px;">Ketuntasan (KKTP {{ $gradebook['kktp'] }})</th>
            </tr>
            <tr>
                @foreach($phCols as $col)
                    <th style="width: 34px;">{{ $col->label }}</th>
                @endforeach
                @foreach($ntCols as $col)
                    <th style="width: 34px;">{{ $col->label }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse($gradebook['rows'] as $row)
                <tr>
                    <td class="text-center">{{ $row['no'] }}</td>
                    <td>{{ $row['student']->full_name }}</td>
                    <td class="text-center">{{ $row['student']->gender }}</td>
                    @foreach($phCols as $col)
                        @php $v = $row['dynamic_scores'][(string) $col->id] ?? null; @endphp
                        <td class="text-center">{{ $v !== null ? rtrim(rtrim(number_format($v, 2), '0'), '.') : '-' }}</td>
                    @endforeach
                    <td class="text-center">{{ $row['attendance_score'] !== null ? rtrim(rtrim(number_format($row['attendance_score'], 2), '0'), '.') : '-' }}</td>
                    @foreach($ntCols as $col)
                        @php $v = $row['dynamic_scores'][(string) $col->id] ?? null; @endphp
                        <td class="text-center">{{ $v !== null ? rtrim(rtrim(number_format($v, 2), '0'), '.') : '-' }}</td>
                    @endforeach
                    <td class="text-center">{{ $row['sts_score'] !== null ? rtrim(rtrim(number_format($row['sts_score'], 2), '0'), '.') : '-' }}</td>
                    <td class="text-center">{{ $row['sas_score'] !== null ? rtrim(rtrim(number_format($row['sas_score'], 2), '0'), '.') : '-' }}</td>
                    <td class="text-center font-bold {{ $row['is_tuntas'] ? 'bg-tuntas' : 'bg-remedial' }}">
                        {{ number_format($row['final_score'], $precision) }}
                    </td>
                    <td class="text-center {{ $row['is_tuntas'] ? 'bg-tuntas' : 'bg-remedial' }}">
                        {{ $row['is_tuntas'] ? 'Tuntas' : 'Remedial' }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ 8 + $phCols->count() + $ntCols->count() }}" class="text-center" style="padding: 14px;">Belum ada daftar peserta didik.</td>
                </tr>
            @endforelse
            @if(count($gradebook['rows']) > 0)
                <tr class="bg-summary">
                    <td colspan="{{ 3 + $phCols->count() + 1 + $ntCols->count() + 2 }}" class="text-right" style="padding-right: 8px;">
                        Rata-Rata Kelas: {{ number_format($gradebook['stats']['class_average'], 2) }} &nbsp;|&nbsp;
                        Tertinggi: {{ number_format($gradebook['stats']['highest_score'], 2) }} &nbsp;|&nbsp;
                        Terendah: {{ number_format($gradebook['stats']['lowest_score'], 2) }}
                    </td>
                    <td class="text-center">{{ number_format($gradebook['stats']['class_average'], $precision) }}</td>
                    <td class="text-center">
                        T: {{ $gradebook['stats']['tuntas_count'] }} / R: {{ $gradebook['stats']['remedial_count'] }}
                    </td>
                </tr>
            @endif
        </tbody>
    </table>

    @include('exports.pdf.partials.signatures')
</body>
</html>
