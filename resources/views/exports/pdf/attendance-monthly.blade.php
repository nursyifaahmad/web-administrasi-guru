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
                    @php
                        $meta = $matrix['days_meta'][$d] ?? [];
                        $isHol = $meta['is_holiday'] ?? false;
                    @endphp
                    <th style="width: 14px; padding: 1px 0; {{ $isHol ? 'background-color: #fee2e2; color: #dc2626;' : '' }}">
                        <div style="font-size: 5pt; line-height: 1;">{{ $meta['day_name'] ?? '' }}</div>
                        <div style="font-size: 6.5pt; font-weight: bold;">{{ $d }}</div>
                    </th>
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
                        @php
                            $st = $row['days'][$d] ?? '';
                            $meta = $matrix['days_meta'][$d] ?? [];
                            $isHol = $meta['is_holiday'] ?? false;
                        @endphp
                        <td class="text-center {{ $st === 'd' ? 'bg-dispen' : ($st === 'a' ? 'bg-remedial' : '') }}"
                            style="{{ ($isHol && $st === '') ? 'background-color: #fef2f2; color: #cbd5e1;' : '' }}">
                            {{ $st ?: ($isHol ? '-' : '') }}
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
        <strong>"a"</strong> = Alpa / Tanpa Keterangan &nbsp;|&nbsp;
        <span style="color: #dc2626; font-weight: bold;">Kolom Merah Muda = Hari Minggu / Libur (KBM Tidak Diadakan)</span>
    </div>

    @if(!empty($matrix['events_in_month']) && $matrix['events_in_month']->isNotEmpty())
        <div style="font-size: 7pt; color: #1e293b; margin-top: 3px; background-color: #f8fafc; border: 1px solid #e2e8f0; padding: 3px 6px;">
            <strong>Catatan Hari Libur &amp; Agenda Bulan Ini:</strong>
            @foreach($matrix['events_in_month'] as $ev)
                <span>&bull; Tgl {{ $ev->event_date->format('d') }}{{ $ev->event_end_date && $ev->event_end_date->ne($ev->event_date) ? ('–' . $ev->event_end_date->format('d')) : '' }}: <strong>{{ $ev->title }}</strong> ({{ $ev->type_label }}{{ $ev->is_holiday ? ', Libur KBM' : '' }})</span>&nbsp;&nbsp;
            @endforeach
        </div>
    @endif

    @include('exports.pdf.partials.signatures')
</body>
</html>
