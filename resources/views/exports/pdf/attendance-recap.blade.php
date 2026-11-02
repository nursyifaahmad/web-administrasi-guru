<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Rekap Absensi Semester - {{ $teachingClass->class_name }}</title>
    @include('exports.pdf.partials.styles')
</head>
<body>
    @include('exports.pdf.partials.header', [
        'documentTitle' => 'REKAPITULASI KEHADIRAN PESERTA DIDIK',
        'documentSubtitle' => $teachingClass->academicYear->full_label . ' (Total Pertemuan Tercatat: ' . $recap['total_active_dates'] . ' Hari)',
    ])

    <table class="data-table">
        <thead>
            <tr>
                <th rowspan="2" style="width: 28px;">No</th>
                <th rowspan="2">Nama Lengkap</th>
                @if($options['show_nickname'] ?? true)
                    <th rowspan="2" style="width: 85px;">Nama Panggilan</th>
                @endif
                <th rowspan="2" style="width: 32px;">L/P</th>
                <th colspan="3">Rincian Kehadiran</th>
                <th colspan="4">Jumlah Ketidakhadiran</th>
                <th rowspan="2" style="width: 55px;">% Hadir</th>
            </tr>
            <tr>
                <th style="width: 45px;">Hadir (.)</th>
                <th style="width: 48px;">Dispen (d)</th>
                <th style="width: 55px;">Jml Hadir</th>
                <th style="width: 42px;">Sakit</th>
                <th style="width: 42px;">Izin</th>
                <th style="width: 42px;">Alfa</th>
                <th style="width: 48px;">Total</th>
            </tr>
        </thead>
        <tbody>
            @forelse($recap['rows'] as $row)
                <tr>
                    <td class="text-center">{{ $row['no'] }}</td>
                    <td>{{ $row['student']->full_name }}</td>
                    @if($options['show_nickname'] ?? true)
                        <td class="text-center">{{ $row['student']->nickname ?: '-' }}</td>
                    @endif
                    <td class="text-center">{{ $row['student']->gender }}</td>
                    <td class="text-center">{{ $row['dot'] }}</td>
                    <td class="text-center">{{ $row['dispen'] }}</td>
                    <td class="text-center font-bold">{{ $row['present'] }}</td>
                    <td class="text-center">{{ $row['sakit'] }}</td>
                    <td class="text-center">{{ $row['izin'] }}</td>
                    <td class="text-center">{{ $row['alfa'] }}</td>
                    <td class="text-center font-bold">{{ $row['absent'] }}</td>
                    <td class="text-center font-bold">{{ number_format($row['percentage'], 1) }}%</td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ ($options['show_nickname'] ?? true) ? 12 : 11 }}" class="text-center" style="padding: 14px;">Belum ada data peserta didik.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    @include('exports.pdf.partials.signatures')
</body>
</html>
