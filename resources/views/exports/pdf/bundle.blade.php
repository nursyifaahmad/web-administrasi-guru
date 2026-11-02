<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Bundel Administrasi Guru - {{ $teachingClass->class_name }}</title>
    @include('exports.pdf.partials.styles')
</head>
<body>
    {{-- HALAMAN 1: SAMPUL BUNDEL ADMINISTRASI GURU --}}
    <div style="border: 3px double #1e293b; padding: 28px 34px; text-align: center;">
        <div style="font-size: 11pt; font-weight: bold; text-transform: uppercase; line-height: 1.15;">{{ $school->gov_header }}</div>
        <div style="font-size: 11pt; font-weight: bold; text-transform: uppercase; line-height: 1.08;">{{ $school->department_header }}</div>
        <div style="font-size: 16pt; font-weight: bold; text-transform: uppercase; line-height: 1.05; margin-top: -1px; margin-bottom: 2px;">{{ $school->school_name }}</div>
        <div style="font-size: 8.5pt; color: #475569; margin-bottom: 24px; line-height: 1.45;">
            @if($school->singleLineAddress() !== '')
                <div style="white-space: nowrap;">{{ $school->singleLineAddress() }}</div>
            @endif
            @if($school->contactAndIdentityLine() !== '')
                <div style="white-space: nowrap;">{{ $school->contactAndIdentityLine() }}</div>
            @endif
        </div>

        <hr style="border: none; border-top: 2px solid #0f172a; margin-bottom: 28px;">

        <div style="font-size: 18pt; font-weight: bold; letter-spacing: 1px; margin-bottom: 6px;">BUKU ADMINISTRASI GURU</div>
        <div style="font-size: 11pt; color: #334155; margin-bottom: 28px;">
            JURNAL HARIAN &bull; DAFTAR HADIR PESERTA DIDIK &bull; DAFTAR NILAI &amp; KKTP
        </div>

        <table style="width: 68%; margin: 0 auto 28px auto; font-size: 10.5pt;">
            <tr>
                <td style="padding: 5px 8px; text-align: left; width: 38%;"><strong>Mata Pelajaran</strong></td>
                <td style="padding: 5px 8px; text-align: left;">: {{ $teachingClass->subject->name ?? '-' }}</td>
            </tr>
            <tr>
                <td style="padding: 5px 8px; text-align: left;"><strong>Kelas / Tingkat</strong></td>
                <td style="padding: 5px 8px; text-align: left;">: {{ $teachingClass->class_name }} (Tingkat {{ $teachingClass->grade_level }})</td>
            </tr>
            <tr>
                <td style="padding: 5px 8px; text-align: left;"><strong>Tahun Ajaran / Semester</strong></td>
                <td style="padding: 5px 8px; text-align: left;">: {{ $teachingClass->academicYear->year_name ?? '-' }} / {{ $teachingClass->academicYear->semester ?? '-' }}</td>
            </tr>
            <tr>
                <td style="padding: 5px 8px; text-align: left;"><strong>Standar KKM / KKTP</strong></td>
                <td style="padding: 5px 8px; text-align: left;">: {{ $teachingClass->effective_kktp }}</td>
            </tr>
            <tr>
                <td style="padding: 5px 8px; text-align: left;"><strong>Guru Mata Pelajaran</strong></td>
                <td style="padding: 5px 8px; text-align: left;">: {{ $teachingClass->teacher->name ?? '-' }} (NIP. {{ $teachingClass->teacher->nip ?: '-' }})</td>
            </tr>
            <tr>
                <td style="padding: 5px 8px; text-align: left;"><strong>Wali Kelas</strong></td>
                <td style="padding: 5px 8px; text-align: left;">: {{ $teachingClass->homeroom_teacher_name }} (NIP. {{ $teachingClass->homeroom_teacher_nip ?: '-' }})</td>
            </tr>
        </table>

        @include('exports.pdf.partials.signatures')
    </div>

    <div class="page-break"></div>

    {{-- BAGIAN 2: JURNAL HARIAN --}}
    @include('exports.pdf.partials.header', [
        'documentTitle' => 'BAGIAN I: JURNAL HARIAN PELAKSANAAN PEMBELAJARAN',
        'documentSubtitle' => 'Periode: ' . $periodLabel,
    ])
    <table class="data-table">
        <thead>
            <tr>
                <th rowspan="2" style="width: 30px;">No</th>
                <th rowspan="2" style="width: 95px;">Tanggal</th>
                <th rowspan="2" style="width: 65px;">Jam Ke</th>
                <th rowspan="2">Kegiatan / Materi Pembelajaran</th>
                <th colspan="2" style="width: 105px;">Jumlah Murid</th>
                <th rowspan="2" style="width: 150px;">Catatan</th>
                <th rowspan="2" style="width: 72px;">Paraf</th>
            </tr>
            <tr>
                <th>Hadir</th>
                <th>Tidak Hadir</th>
            </tr>
        </thead>
        <tbody>
            @forelse($journals as $idx => $journal)
                <tr>
                    <td class="text-center">{{ $idx + 1 }}</td>
                    <td class="text-center">{{ \App\Support\IndonesianDate::dayName($journal->journal_date) }}, {{ $journal->journal_date->format('d/m/Y') }}</td>
                    <td class="text-center">{{ $journal->lesson_hours }}</td>
                    <td>{{ $journal->activity_material }}</td>
                    <td class="text-center font-bold">{{ $journal->present_count }}</td>
                    <td class="text-center font-bold">{{ $journal->absent_count }}</td>
                    <td>{{ $journal->notes }}</td>
                    <td class="text-center">
                        @if($options['show_journal_paraf'] ?? true)
                            @php
                                $pRaw = $journal->signature_data ?: $teachingClass->teacher?->signature_data;
                                $pNorm = \App\Support\SignatureImageHelper::normalizeInk($pRaw);
                            @endphp
                            @if($pNorm)
                                <img src="{{ $pNorm['data_uri'] }}" style="max-height: 36px; max-width: 72px; margin-top: -4px; margin-bottom: -4px; position: relative; z-index: 5;" alt="Paraf">
                            @endif
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-center">Belum ada data Jurnal Harian.</td></tr>
            @endforelse
        </tbody>
    </table>
    @include('exports.pdf.partials.signatures')

    <div class="page-break"></div>

    {{-- BAGIAN 3: ABSENSI BULANAN --}}
    @include('exports.pdf.partials.header', [
        'documentTitle' => 'BAGIAN II: DAFTAR HADIR (ABSENSI) PESERTA DIDIK',
        'documentSubtitle' => 'Bulan: ' . $monthLabel,
    ])
    <table class="data-table compact-matrix">
        <thead>
            <tr>
                <th rowspan="2" style="width: 22px;">No</th>
                <th rowspan="2" style="width: {{ ($options['show_nickname'] ?? true) ? '145px' : '205px' }};">Nama Lengkap</th>
                @if($options['show_nickname'] ?? true)
                    <th rowspan="2" style="width: 60px;">Panggilan</th>
                @endif
                <th rowspan="2" style="width: 24px;">L/P</th>
                <th colspan="{{ $matrix['days_in_month'] }}">Tanggal ({{ $monthLabel }})</th>
                <th rowspan="2" style="width: 34px;">Hadir</th>
                <th colspan="3">Ketidakhadiran</th>
            </tr>
            <tr>
                @for($d = 1; $d <= $matrix['days_in_month']; $d++)
                    <th style="width: 14px;">{{ $d }}</th>
                @endfor
                <th>S</th>
                <th>I</th>
                <th>A</th>
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
                        <td class="text-center">{{ $row['days'][$d] ?? '' }}</td>
                    @endfor
                    <td class="text-center font-bold">{{ $row['present'] }}</td>
                    <td class="text-center">{{ $row['sakit'] }}</td>
                    <td class="text-center">{{ $row['izin'] }}</td>
                    <td class="text-center">{{ $row['alfa'] }}</td>
                </tr>
            @empty
                <tr><td colspan="{{ (($options['show_nickname'] ?? true) ? 8 : 7) + $matrix['days_in_month'] }}" class="text-center">Belum ada peserta didik.</td></tr>
            @endforelse
        </tbody>
    </table>
    @include('exports.pdf.partials.signatures')

    <div class="page-break"></div>

    {{-- BAGIAN 4: DAFTAR NILAI --}}
    @php
        $phCols = $gradebook['ph_columns'];
        $ntCols = $gradebook['nt_columns'];
        $w = $gradebook['weight'];
        $precision = (int) $w->rounding_precision;
    @endphp
    @include('exports.pdf.partials.header', [
        'documentTitle' => 'BAGIAN III: DAFTAR NILAI PESERTA DIDIK',
        'documentSubtitle' => sprintf(
            'Bobot NA: PH (%s%%) + Kehadiran (%s%%) + NT (%s%%) + STS (%s%%) + SAS (%s%%) = 100%%',
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
                <th rowspan="2" style="width: 44px;">Kehadiran</th>
                @if($ntCols->count() > 0)
                    <th colspan="{{ $ntCols->count() }}">Nilai Tugas</th>
                @endif
                <th rowspan="2" style="width: 38px;">STS</th>
                <th rowspan="2" style="width: 38px;">SAS</th>
                <th rowspan="2" style="width: 44px;">NA</th>
                <th rowspan="2" style="width: 64px;">Ketuntasan</th>
            </tr>
            <tr>
                @foreach($phCols as $col)
                    <th>{{ $col->label }}</th>
                @endforeach
                @foreach($ntCols as $col)
                    <th>{{ $col->label }}</th>
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
                <tr><td colspan="{{ 8 + $phCols->count() + $ntCols->count() }}" class="text-center">Belum ada peserta didik.</td></tr>
            @endforelse
        </tbody>
    </table>
    @include('exports.pdf.partials.signatures')
</body>
</html>
