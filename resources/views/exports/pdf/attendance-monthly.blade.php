@php
    $onlyTeachingDays = !empty($options['only_teaching_days']) && !empty($matrix['has_teaching_days']);
    $activeDays = [];
    for ($d = 1; $d <= $matrix['days_in_month']; $d++) {
        $meta = $matrix['days_meta'][$d] ?? [];
        $hasData = false;
        if ($onlyTeachingDays) {
            foreach ($matrix['rows'] as $row) {
                if (!empty($row['days'][$d])) {
                    $hasData = true;
                    break;
                }
            }
        }
        if (!$onlyTeachingDays || !empty($meta['is_teaching_day']) || $hasData) {
            $activeDays[] = $d;
        }
    }
    if (empty($activeDays)) {
        $activeDays = range(1, $matrix['days_in_month']);
    }
    $totalActiveDays = count($activeDays);
    $showNickname = $options['show_nickname'] ?? true;
    $dayWidth = $totalActiveDays <= 14 ? '25px' : ($totalActiveDays <= 20 ? '20px' : '14px');
    $nameWidth = $totalActiveDays <= 14 
        ? ($showNickname ? '195px' : '270px') 
        : ($showNickname ? '145px' : '207px');
@endphp

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
        'documentSubtitle' => 'Bulan: ' . $monthLabel . ($onlyTeachingDays ? ' (Pertemuan KBM Efektif)' : ''),
    ])

    <table class="data-table compact-matrix">
        <thead>
            <tr>
                <th rowspan="2" style="width: 22px;">No</th>
                <th rowspan="2" style="width: {{ $nameWidth }};">Nama Lengkap</th>
                @if($showNickname)
                    <th rowspan="2" style="width: {{ $totalActiveDays <= 14 ? '75px' : '62px' }};">Nama Panggilan</th>
                @endif
                <th rowspan="2" style="width: 24px;">L/P</th>
                <th colspan="{{ $totalActiveDays }}">Bulan {{ $monthLabel }} (Tanggal Pertemuan)</th>
                <th rowspan="2" style="width: 34px;">Jml Hadir</th>
                <th colspan="3">Jumlah Ketidakhadiran</th>
            </tr>
            <tr>
                @foreach($activeDays as $d)
                    @php
                        $meta = $matrix['days_meta'][$d] ?? [];
                        $isHol = $meta['is_holiday'] ?? false;
                    @endphp
                    <th style="width: {{ $dayWidth }}; padding: 1px 0; {{ $isHol ? 'background-color: #fee2e2; color: #dc2626;' : '' }}">
                        <div style="font-size: {{ $totalActiveDays <= 14 ? '5.5pt' : '5pt' }}; line-height: 1;">{{ $meta['day_name'] ?? '' }}</div>
                        <div style="font-size: {{ $totalActiveDays <= 14 ? '7pt' : '6.5pt' }}; font-weight: bold;">{{ $d }}</div>
                    </th>
                @endforeach
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
                    @if($showNickname)
                        <td class="text-center">{{ $row['student']->nickname ?: '-' }}</td>
                    @endif
                    <td class="text-center">{{ $row['student']->gender }}</td>
                    @foreach($activeDays as $d)
                        @php
                            $st = $row['days'][$d] ?? '';
                            $meta = $matrix['days_meta'][$d] ?? [];
                            $isHol = $meta['is_holiday'] ?? false;
                        @endphp
                        <td class="text-center {{ $st === 'd' ? 'bg-dispen' : ($st === 'a' ? 'bg-remedial' : '') }}"
                            style="{{ ($isHol && $st === '') ? 'background-color: #fef2f2; color: #cbd5e1;' : '' }}">
                            {{ $st ?: ($isHol ? '-' : '') }}
                        </td>
                    @endforeach
                    <td class="text-center font-bold">{{ $row['present'] }}</td>
                    <td class="text-center">{{ $row['sakit'] }}</td>
                    <td class="text-center">{{ $row['izin'] }}</td>
                    <td class="text-center">{{ $row['alfa'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ ($showNickname ? 8 : 7) + $totalActiveDays }}" class="text-center" style="padding: 14px;">Belum ada daftar peserta didik di kelas ini.</td>
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
        @if($onlyTeachingDays)
            &nbsp;|&nbsp; <span style="color: #047857; font-weight: bold;">Mode Tanggal KBM: Kolom di luar jadwal mengajar otomatis disembunyikan.</span>
        @else
            &nbsp;|&nbsp; <span style="color: #dc2626; font-weight: bold;">Kolom Merah Muda = Hari Minggu / Libur (KBM Tidak Diadakan)</span>
        @endif
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
