@if($options['show_header'] ?? true)
@php
    $leftLogo = $school->resolvedLogoBox('left');
    $rightLogo = $school->resolvedLogoBox('right');
    $sideCellWidth = max($leftLogo['cell_width'], $rightLogo['cell_width']);
@endphp
<table class="kop-table">
    <tr>
        <td style="width: {{ $sideCellWidth }}px; text-align: center;">
            @if(!empty($school->logo_left_data))
                <img src="{{ $school->logo_left_data }}"
                     width="{{ $leftLogo['width'] }}"
                     height="{{ $leftLogo['height'] }}"
                     style="width: {{ $leftLogo['width'] }}px; height: {{ $leftLogo['height'] }}px;"
                     alt="Logo Kiri">
            @endif
        </td>
        <td style="text-align: center; padding: 0 8px;">
            @if(!empty($school->gov_header))
                <div class="kop-gov">{{ $school->gov_header }}</div>
            @endif
            @if(!empty($school->department_header))
                <div class="kop-dept">{{ $school->department_header }}</div>
            @endif
            <div class="kop-school">{{ $school->school_name }}</div>
            <div class="kop-address">
                NPSN: {{ $school->npsn }}
                @if(!empty($school->nss)) | NSS: {{ $school->nss }} @endif
                @if(!empty($school->address)) <br>{{ $school->address }} @endif
                @if(!empty($school->phone) || !empty($school->email))
                    <br>
                    @if(!empty($school->phone)) Telp: {{ $school->phone }} @endif
                    @if(!empty($school->email)) | Email: {{ $school->email }} @endif
                @endif
            </div>
        </td>
        <td style="width: {{ $sideCellWidth }}px; text-align: center;">
            @if(!empty($school->logo_right_data))
                <img src="{{ $school->logo_right_data }}"
                     width="{{ $rightLogo['width'] }}"
                     height="{{ $rightLogo['height'] }}"
                     style="width: {{ $rightLogo['width'] }}px; height: {{ $rightLogo['height'] }}px;"
                     alt="Logo Kanan">
            @endif
        </td>
    </tr>
</table>
@endif

<div class="doc-title">{{ $documentTitle }}</div>
@if(!empty($documentSubtitle))
    <div class="doc-subtitle">{{ $documentSubtitle }}</div>
@endif

<table class="meta-table">
    <tr>
        <td style="width: 16%;"><strong>Mata Pelajaran</strong></td>
        <td style="width: 34%;">: {{ $teachingClass->subject->name ?? '-' }}</td>
        <td style="width: 16%;"><strong>Tahun Ajaran</strong></td>
        <td style="width: 34%;">: {{ $teachingClass->academicYear->year_name ?? '-' }} (Semester {{ $teachingClass->academicYear->semester ?? '-' }})</td>
    </tr>
    <tr>
        <td><strong>Kelas / Tingkat</strong></td>
        <td>: {{ $teachingClass->class_name }} (Kelas {{ $teachingClass->grade_level }})</td>
        <td><strong>KKM / KKTP</strong></td>
        <td>: <strong>{{ $teachingClass->effective_kktp }}</strong></td>
    </tr>
    <tr>
        <td><strong>Guru Pengampu</strong></td>
        <td>: {{ $teachingClass->teacher->name ?? '-' }}</td>
        <td><strong>Wali Kelas</strong></td>
        <td>: {{ $teachingClass->homeroom_teacher_name }}</td>
    </tr>
</table>
