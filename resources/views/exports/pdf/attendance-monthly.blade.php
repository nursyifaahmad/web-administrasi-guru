<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Absensi {{ $monthLabel }} - {{ $teachingClass->class_name }}</title>
    @include('exports.pdf.partials.styles')
</head>
<body>
    @include('exports.pdf.partials.header', [
        'documentTitle' => 'DAFTAR HADIR (ABSENSI) PESERTA DIDIK',
        'documentSubtitle' => 'Bulan: ' . $monthLabel,
    ])

    <table class="data-table compact-matrix">
        <thead>
            <tr>
                <th rowspan="2" style="width: 22px;">No</th>
                <th rowspan="2" style="width: {{ ($options['show_nickname'] ?? true) ? '145px' : '207px' }};">Nama Lengkap</th>
                @if($options['show_nickname'] ?? true)
                    <th rowspan="2" style="width: 62px;">Nama Panggilan</th>
                @endif
                <th rowspan="2" style="width: 24px;">L/P</th>
                <th colspan="{{ $matrix['days_in_month'] }}">Bulan {{ $monthLabel }} (Tanggal)</th>
                <th rowspan="2" style="width: 34px;">Jml Hadir</th>
                <th colspan="3">Jumlah Ketidakhadiran</th>
            </tr>
            <tr>
                @for($d = 1; $d <= $matrix['days_in_month']; $d++)
                    <th style="width: 14px;">{{ $d }}</th>
                @endfor
                <th style="width: 22px;">S</th>
                <th style="width: 22px;">I</th>
                <th style="width: 22px;">A</th>
            </tr>
        </thead>
        <tbody>
            @forelse($matrix['rows'] as $row)
                <tr>
                    <td class="text-center">{{ $row['no'] }}</td>
                    <td>{{ $row['student']->full_name }}</td>
                    @if($options['show_nickname'] ?? true)
                        <td class="text-center">{{ $row['student']->nickname ?: '-' }}</td>
                    @endif
                    <td class="text-center">{{ $row['student']->gender }}</td>
                    @for($d = 1; $d <= $matrix['days_in_month']; $d++)
                        @php $st = $row['days'][$d] ?? ''; @endphp
                        <td class="text-center {{ $st === 'd' ? 'bg-dispen' : ($st === 'a' ? 'bg-remedial' : '') }}">
                            {{ $st }}
                        </td>
                    @endfor
                    <td class="text-center font-bold">{{ $row['present'] }}</td>
                    <td class="text-center">{{ $row['sakit'] }}</td>
                    <td class="text-center">{{ $row['izin'] }}</td>
                    <td class="text-center">{{ $row['alfa'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ (($options['show_nickname'] ?? true) ? 8 : 7) + $matrix['days_in_month'] }}" class="text-center" style="padding: 14px;">Belum ada daftar peserta didik di kelas ini.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div style="font-size: 7.5pt; color: #334155; margin-top: 5px;">
        <strong>Keterangan Kode:</strong>
        <strong>"."</strong> = Hadir &nbsp;|&nbsp;
        <strong>"d"</strong> = Dispensasi (tetap dihitung ke dalam Jumlah Hadir) &nbsp;|&nbsp;
        <strong>"s"</strong> = Sakit &nbsp;|&nbsp;
        <strong>"i"</strong> = Izin &nbsp;|&nbsp;
        <strong>"a"</strong> = Alpa / Tanpa Keterangan
    </div>

    @include('exports.pdf.partials.signatures')
</body>
</html>
