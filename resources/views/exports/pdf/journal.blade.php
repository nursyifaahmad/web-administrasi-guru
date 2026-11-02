<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Jurnal Harian - {{ $teachingClass->class_name }}</title>
    @include('exports.pdf.partials.styles')
</head>
<body>
    @include('exports.pdf.partials.header', [
        'documentTitle' => 'JURNAL HARIAN PELAKSANAAN PEMBELAJARAN',
        'documentSubtitle' => 'Periode: ' . $periodLabel,
    ])

    <table class="data-table">
        <thead>
            <tr>
                <th rowspan="2" style="width: 32px;">No</th>
                <th rowspan="2" style="width: 98px;">Tanggal</th>
                <th rowspan="2" style="width: 65px;">Jam Pelajaran Ke</th>
                <th rowspan="2">Kegiatan / Materi Pembelajaran</th>
                <th colspan="2" style="width: 110px;">Jumlah Murid</th>
                <th rowspan="2" style="width: 155px;">Catatan</th>
                <th rowspan="2" style="width: 75px;">Paraf</th>
            </tr>
            <tr>
                <th style="width: 55px;">Hadir</th>
                <th style="width: 55px;">Tidak Hadir</th>
            </tr>
        </thead>
        <tbody>
            @forelse($journals as $index => $journal)
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td class="text-center">
                        {{ \App\Support\IndonesianDate::dayName($journal->journal_date) }},<br>
                        {{ $journal->journal_date->format('d/m/Y') }}
                    </td>
                    <td class="text-center">{{ $journal->lesson_hours }}</td>
                    <td>{{ $journal->activity_material }}</td>
                    <td class="text-center font-bold">{{ $journal->present_count }}</td>
                    <td class="text-center font-bold">{{ $journal->absent_count }}</td>
                    <td>
                        {{ $journal->notes }}
                        @if(!empty($journal->attendance_breakdown))
                            <div style="font-size: 7pt; color: #475569; margin-top: 2px;">({{ $journal->attendance_breakdown }})</div>
                        @endif
                    </td>
                    <td class="text-center" style="height: 32px;">
                        @if($options['show_journal_paraf'] ?? true)
                            @php
                                $parafRaw = $journal->signature_data ?: $teachingClass->teacher?->signature_data;
                                $parafNorm = \App\Support\SignatureImageHelper::normalizeInk($parafRaw);
                            @endphp
                            @if($parafNorm)
                                <img src="{{ $parafNorm['data_uri'] }}" style="max-height: 38px; max-width: 74px; margin-top: -4px; margin-bottom: -4px; position: relative; z-index: 5;" alt="Paraf">
                            @endif
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="text-center" style="padding: 16px;">Belum ada data Jurnal Harian pada periode ini.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    @include('exports.pdf.partials.signatures')
</body>
</html>
