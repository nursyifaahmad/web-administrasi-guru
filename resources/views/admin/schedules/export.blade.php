<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cetak Master Jadwal Pelajaran - {{ $academicYear->year_name ?: $academicYear->semester }}</title>
    @php
        $isTwoShift = (bool) ($exportData['is_two_shift'] ?? false);
        $isPdf = (bool) ($isPdf ?? false);
        $paperSize = $paperSize ?? 'F4';
        $orientation = $orientation ?? ($isTwoShift ? 'landscape' : 'portrait');
        $printDate = $printDate ?? now()->toDateString();
        $showDigitalSignature = $showDigitalSignature ?? true;
        $titimangsa = $titimangsa ?? \App\Support\IndonesianDate::titimangsa($school->default_city ?: 'Lemahabang', $printDate);
        $headmasterSignatureBox = $headmasterSignatureBox ?? (
            ($showDigitalSignature && ! empty($school->headmaster_signature))
                ? \App\Support\SignatureImageHelper::wetSignatureRenderBox($school->headmaster_signature, 28, 56, 150)
                : null
        );
        $cssPageSize = match ($paperSize) {
            'F4' => $orientation === 'landscape' ? '330mm 215mm' : '215mm 330mm',
            'A3' => 'A3 ' . $orientation,
            default => 'A4 ' . $orientation,
        };
        $baseFontPt = $isTwoShift ? ($isPdf ? '5.4pt' : '5.8pt') : ($isPdf ? '6.4pt' : '6.8pt');
        $legendFontPt = $isTwoShift ? ($isPdf ? '5.1pt' : '5.5pt') : ($isPdf ? '6.1pt' : '6.4pt');
        $leftLogo = $school->resolvedLogoBox('left')['data_uri'] ?? null;
        $rightLogo = $school->resolvedLogoBox('right')['data_uri'] ?? null;
    @endphp
    <style>
        @page {
            size: {{ $cssPageSize }};
            margin: {{ $isPdf ? '5mm' : '6mm' }};
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'DejaVu Sans', Arial, Helvetica, sans-serif;
            color: #000000;
            background: {{ $isPdf ? '#ffffff' : '#e2e8f0' }};
            margin: 0;
            padding: {{ $isPdf ? '0' : '16px 12px' }};
            font-size: {{ $baseFontPt }};
            line-height: 1.15;
        }

        .no-print {
            max-width: 1280px;
            margin: 0 auto 14px auto;
            background: #ffffff;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            padding: 10px 14px;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            box-shadow: 0 2px 6px rgba(15, 23, 42, 0.08);
            font-family: Arial, sans-serif;
            font-size: 12px;
        }

        .toolbar-group {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 8px;
        }

        .badge-mode {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 6px;
            font-weight: 700;
            font-size: 11px;
            background: {{ $isTwoShift ? '#e0f2fe' : '#dcfce7' }};
            color: {{ $isTwoShift ? '#0369a1' : '#15803d' }};
            border: 1px solid {{ $isTwoShift ? '#7dd3fc' : '#86efac' }};
        }

        .badge-warn {
            display: inline-block;
            padding: 4px 9px;
            border-radius: 6px;
            font-weight: 700;
            font-size: 11px;
            background: #fee2e2;
            color: #b91c1c;
            border: 1px solid #fca5a5;
        }

        .toolbar-select {
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            padding: 5px 8px;
            font-size: 11.5px;
            font-weight: 600;
            background: #f8fafc;
            color: #0f172a;
        }

        .btn-action {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 6px 12px;
            border-radius: 7px;
            font-weight: 700;
            font-size: 11.5px;
            text-decoration: none;
            cursor: pointer;
            border: none;
        }

        .btn-print { background: #1e293b; color: #ffffff; }
        .btn-pdf { background: #dc2626; color: #ffffff; }
        .btn-excel { background: #059669; color: #ffffff; }
        .btn-back { background: #ffffff; color: #334155; border: 1px solid #cbd5e1; }

        .sheet-page {
            background: #ffffff;
            margin: 0 auto;
            width: {{ $isPdf ? '100%' : ($orientation === 'landscape' ? '318mm' : '203mm') }};
            min-height: {{ $isPdf ? 'auto' : '100mm' }};
            padding: {{ $isPdf ? '0' : '6mm' }};
            {{ $isPdf ? '' : 'box-shadow: 0 4px 18px rgba(15, 23, 42, 0.14); border-radius: 4px;' }}
        }

        .kop-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 5px;
        }

        .kop-table td {
            vertical-align: middle;
        }

        .kop-logo-cell {
            width: 58px;
            text-align: center;
        }

        .kop-logo-cell img {
            max-width: 50px;
            max-height: 46px;
            object-fit: contain;
        }

        .kop-center {
            text-align: center;
            line-height: 1.25;
        }

        .kop-title {
            font-size: {{ $isTwoShift ? '10pt' : '11.5pt' }};
            font-weight: 800;
            letter-spacing: 0.4px;
            text-transform: uppercase;
        }

        .kop-sub {
            font-size: {{ $isTwoShift ? '8.5pt' : '9.5pt' }};
            font-weight: 700;
            text-transform: uppercase;
        }

        .kop-school {
            font-size: {{ $isTwoShift ? '7pt' : '8pt' }};
            font-weight: 700;
            margin-top: 1px;
            color: #1e293b;
        }

        .poster-columns {
            width: 100%;
            border-collapse: collapse;
        }

        .poster-columns > tbody > tr > td {
            vertical-align: top;
            padding: 0;
        }

        .col-gap {
            width: {{ $isTwoShift ? '5px' : '7px' }};
        }

        .col-sidebar {
            width: {{ $isTwoShift ? '24%' : '29%' }};
        }

        .col-shift-half {
            width: auto;
        }

        .col-shift-full {
            width: 70%;
        }

        .shift-banner {
            text-align: center;
            font-weight: 800;
            font-size: {{ $isTwoShift ? '6.2pt' : '7pt' }};
            padding: 2px 4px;
            border: 1.4px solid #000000;
            border-bottom: none;
            text-transform: uppercase;
        }

        .shift-banner-pagi {
            background: #e2e8f0;
        }

        .shift-banner-siang {
            background: #e0f2fe;
        }

        table.sched-grid {
            width: 100%;
            border-collapse: collapse;
            border: 1.4px solid #000000;
            font-size: {{ $baseFontPt }};
        }

        table.sched-grid th,
        table.sched-grid td {
            border: 0.6px solid #000000;
            text-align: center;
            vertical-align: middle;
            padding: {{ $isTwoShift ? '1px 1px' : '1.4px 1.5px' }};
        }

        table.sched-grid thead th {
            background: #d9d9d9;
            font-weight: 800;
        }

        .col-hari { width: {{ $isTwoShift ? '14px' : '18px' }}; }
        .col-jp { width: {{ $isTwoShift ? '15px' : '19px' }}; }
        .col-waktu { width: {{ $isTwoShift ? '43px' : '54px' }}; }
        .col-sec { font-weight: 700; background: #e5e5e5 !important; }

        .cell-hari {
            background: #f3f4f6;
            font-weight: 800;
            font-size: {{ $isTwoShift ? '5pt' : '6pt' }};
            line-height: 1.02;
            padding: 1px 0 !important;
        }

        .hari-vertical {
            display: inline-block;
            writing-mode: vertical-rl;
            transform: rotate(180deg);
            white-space: nowrap;
            letter-spacing: 0.4px;
        }

        .cell-jp {
            font-weight: 700;
        }

        .cell-waktu {
            font-size: {{ $isTwoShift ? '4.8pt' : '5.8pt' }};
            white-space: nowrap;
        }

        .cell-code {
            font-weight: 700;
        }

        .cell-activity {
            font-weight: 800;
            font-style: italic;
            font-size: {{ $isTwoShift ? '4.6pt' : '5.6pt' }};
            letter-spacing: -0.1px;
        }

        .bg-yellow {
            background: #ffff00 !important;
            color: #000000 !important;
        }

        .bg-conflict {
            background: #ef4444 !important;
            color: #ffffff !important;
        }

        tr.day-end-row td {
            border-bottom: 1.4px solid #000000 !important;
        }

        table.legend-grid {
            width: 100%;
            border-collapse: collapse;
            border: 1.2px solid #000000;
            font-size: {{ $legendFontPt }};
            margin-bottom: 4px;
        }

        table.legend-grid th,
        table.legend-grid td {
            border: 0.6px solid #000000;
            padding: {{ $isTwoShift ? '1px 2px' : '1.4px 3px' }};
            vertical-align: middle;
        }

        table.legend-grid thead th {
            background: #d9d9d9;
            font-weight: 800;
            text-align: center;
        }

        .td-center { text-align: center; }
        .td-left { text-align: left; }

        .signature-box {
            margin-top: 7px;
            font-size: {{ $legendFontPt }};
            line-height: 1.28;
            padding-left: 4px;
            page-break-inside: avoid;
        }

        .sig-space-filled {
            line-height: 0;
        }

        .sig-space-empty {
            height: {{ $isTwoShift ? '24px' : '30px' }};
        }

        .sig-img {
            display: block;
            margin-left: 4px;
            position: relative;
            z-index: 10;
        }

        @media print {
            body {
                background: #ffffff;
                padding: 0;
                margin: 0;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .no-print {
                display: none !important;
            }
            .sheet-page {
                width: 100%;
                padding: 0;
                margin: 0;
                box-shadow: none;
                border-radius: 0;
            }
        }
    </style>
</head>
<body>
    @if(! $isPdf)
        <div class="no-print">
            <div class="toolbar-group">
                <a href="{{ route('admin.schedules.master.index', ['academic_year_id' => $academicYear->id]) }}" class="btn-action btn-back">
                    ← Kembali
                </a>
                <span class="badge-mode">
                    {{ $isTwoShift ? 'Mode 2 Shift (Side-by-Side)' : 'Mode 1 Shift (Semua Rombel)' }}
                </span>
                <span style="font-size: 11px; color: #475569; font-weight: 600;">
                    {{ $exportData['total_items'] ?? 0 }} Slot
                </span>
                @if(($exportData['total_conflicts'] ?? 0) > 0)
                    <span class="badge-warn">{{ $exportData['total_conflicts'] }} Bentrok (Merah)</span>
                @endif
            </div>

            <div class="toolbar-group">
                <form method="GET" action="{{ route('admin.schedules.master.export') }}" class="toolbar-group">
                    <input type="hidden" name="academic_year_id" value="{{ $academicYear->id }}">
                    <label style="font-size: 11px; color: #475569; font-weight: 600;">Kertas:
                        <select name="paper_size" onchange="this.form.submit()" class="toolbar-select">
                            <option value="F4" {{ $paperSize === 'F4' ? 'selected' : '' }}>F4 (215×330mm)</option>
                            <option value="A4" {{ $paperSize === 'A4' ? 'selected' : '' }}>A4</option>
                            <option value="A3" {{ $paperSize === 'A3' ? 'selected' : '' }}>A3</option>
                        </select>
                    </label>
                    <label style="font-size: 11px; color: #475569; font-weight: 600;">Orientasi:
                        <select name="orientation" onchange="this.form.submit()" class="toolbar-select">
                            <option value="portrait" {{ $orientation === 'portrait' ? 'selected' : '' }}>Portrait</option>
                            <option value="landscape" {{ $orientation === 'landscape' ? 'selected' : '' }}>Landscape</option>
                        </select>
                    </label>
                    <label style="font-size: 11px; color: #475569; font-weight: 600;">Tgl Cetak:
                        <input type="date" name="print_date" value="{{ $printDate }}" onchange="this.form.submit()" class="toolbar-select">
                    </label>
                    <label style="display: inline-flex; align-items: center; gap: 4px; font-size: 11px; color: #334155; font-weight: 600; cursor: pointer;">
                        <input type="hidden" name="show_digital_signature" value="0">
                        <input type="checkbox" name="show_digital_signature" value="1" {{ $showDigitalSignature ? 'checked' : '' }} onchange="this.form.submit()">
                        <span>Tampilkan TTD Digital</span>
                    </label>
                </form>

                <button type="button" onclick="window.print()" class="btn-action btn-print">
                    Print / Save PDF Browser
                </button>
                <a href="{{ route('admin.schedules.master.export', ['academic_year_id' => $academicYear->id, 'paper_size' => $paperSize, 'orientation' => $orientation, 'print_date' => $printDate, 'show_digital_signature' => $showDigitalSignature ? '1' : '0', 'download' => 1]) }}"
                   class="btn-action btn-pdf">
                    Download PDF (.pdf)
                </a>
                <a href="{{ route('admin.schedules.master.export-excel', ['academic_year_id' => $academicYear->id, 'print_date' => $printDate, 'show_digital_signature' => $showDigitalSignature ? '1' : '0']) }}"
                   class="btn-action btn-excel">
                    Export Excel (.xlsx)
                </a>
            </div>
        </div>
    @endif

    <div class="sheet-page">
        {{-- Compact Kop Poster --}}
        <table class="kop-table">
            <tr>
                <td class="kop-logo-cell">
                    @if($leftLogo)
                        <img src="{{ $leftLogo }}" alt="Logo Kiri">
                    @endif
                </td>
                <td class="kop-center">
                    <div class="kop-title">JADWAL PELAJARAN</div>
                    <div class="kop-sub">SEMESTER {{ strtoupper((string) ($academicYear->semester ?: 'GANJIL')) }}</div>
                    <div class="kop-sub">TAHUN PELAJARAN {{ $academicYear->year_name ?: '-' }}</div>
                    <div class="kop-school">
                        {{ strtoupper((string) ($school->school_name ?: 'SMP NEGERI 2 LEMAHABANG')) }} — Jadwal Pelajaran Sekolah
                    </div>
                </td>
                <td class="kop-logo-cell">
                    @if($rightLogo)
                        <img src="{{ $rightLogo }}" alt="Logo Kanan">
                    @endif
                </td>
            </tr>
        </table>

        {{-- Matriks Jadwal (1 Shift atau 2 Shift Berdampingan) + Panel Legenda Kanan --}}
        <table class="poster-columns">
            <tbody>
                <tr>
                    <td class="{{ $isTwoShift ? 'col-shift-half' : 'col-shift-full' }}">
                        @include('admin.schedules.partials.export-shift-table', [
                            'shift' => $exportData['shift1'],
                            'shiftTitle' => $isTwoShift ? 'SHIFT 1 (PAGI)' : null,
                            'isPdf' => $isPdf,
                        ])
                    </td>

                    @if($isTwoShift)
                        <td class="col-gap"></td>
                        <td class="col-shift-half">
                            @include('admin.schedules.partials.export-shift-table', [
                                'shift' => $exportData['shift2'],
                                'shiftTitle' => 'SHIFT 2 (SIANG)',
                                'isPdf' => $isPdf,
                            ])
                        </td>
                    @endif

                    <td class="col-gap"></td>
                    <td class="col-sidebar">
                        {{-- Tabel 1: Daftar Kode Guru --}}
                        <table class="legend-grid">
                            <thead>
                                <tr>
                                    <th style="width: 14%;">Kode</th>
                                    <th style="width: 50%;">Nama Guru</th>
                                    <th style="width: 36%;">Mata Pelajaran</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($exportData['teachers'] as $teacher)
                                    <tr>
                                        <td class="td-center"><strong>{{ $teacher->code }}</strong></td>
                                        <td class="td-left">{{ $teacher->name }}</td>
                                        <td class="td-left">{{ $teacher->subject }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="td-center" style="color: #64748b;">Belum ada data guru</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>

                        {{-- Tabel 2: Daftar Guru Piket --}}
                        <table class="legend-grid">
                            <thead>
                                <tr>
                                    <th style="width: 14%;">No</th>
                                    <th style="width: 56%;">NAMA GURU PIKET</th>
                                    <th style="width: 30%;">HARI</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($exportData['piket_list'] as $idx => $piket)
                                    <tr>
                                        <td class="td-center">{{ $idx + 1 }}</td>
                                        <td class="td-left">{{ $piket->name }}</td>
                                        <td class="td-center"><strong>{{ strtoupper((string) $piket->piket_day) }}</strong></td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="td-center" style="color: #64748b;">Belum ada guru piket</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>

                        {{-- Tabel 3: Daftar Wali Kelas --}}
                        <table class="legend-grid">
                            <thead>
                                <tr>
                                    <th style="width: 14%;">No</th>
                                    <th style="width: 56%;">NAMA GURU</th>
                                    <th style="width: 30%;">WALI KELAS</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($exportData['homeroom_list'] as $idx => $hr)
                                    <tr>
                                        <td class="td-center">{{ $idx + 1 }}</td>
                                        <td class="td-left">{{ ($hr['teacher_name'] ?? '') === 'Belum Ditentukan' ? '-' : $hr['teacher_name'] }}</td>
                                        <td class="td-center"><strong>{{ $hr['class_code'] ?? '' }}</strong></td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="td-center" style="color: #64748b;">Belum ada wali kelas</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>

                        {{-- Blok Tanda Tangan & Identitas Kepala Sekolah (Sumber: Kop Sekolah & Tahun Ajaran) --}}
                        <div class="signature-box">
                            <div>{{ $titimangsa }}</div>
                            <div>Kepala {{ $school->school_name ?: 'SMP Negeri 2 Lemahabang' }}</div>
                            @if(!empty($headmasterSignatureBox))
                                <div class="sig-space-filled">
                                    <img src="{{ $headmasterSignatureBox['data_uri'] }}"
                                         class="sig-img"
                                         width="{{ $headmasterSignatureBox['width'] }}"
                                         height="{{ $headmasterSignatureBox['height'] }}"
                                         style="width: {{ $headmasterSignatureBox['width'] }}px; height: {{ $headmasterSignatureBox['height'] }}px; margin-top: {{ $headmasterSignatureBox['margin_top'] }}px; margin-bottom: {{ $headmasterSignatureBox['margin_bottom'] }}px;"
                                         alt="TTD Kepala Sekolah">
                                </div>
                            @else
                                <div class="sig-space-empty"></div>
                            @endif
                            <div style="font-weight: 800; text-decoration: underline;">
                                {{ $school->headmaster_name ?: '........................................' }}
                            </div>
                            <div>NIP. {{ $school->headmaster_nip ?: '-' }}</div>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</body>
</html>
