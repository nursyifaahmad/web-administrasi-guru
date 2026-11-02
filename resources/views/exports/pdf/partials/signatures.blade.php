@php
    $showSig = $options['show_digital_signature'] ?? true;
    $wkSigBox = $showSig
        ? \App\Support\SignatureImageHelper::wetSignatureRenderBox($teachingClass->homeroom_teacher_signature, 40, 104, 260)
        : null;
    $guruSigBox = $showSig
        ? \App\Support\SignatureImageHelper::wetSignatureRenderBox($teachingClass->teacher?->signature_data, 40, 104, 260)
        : null;
    $hmSigBox = ($showSig && ($options['show_headmaster'] ?? false))
        ? \App\Support\SignatureImageHelper::wetSignatureRenderBox($school->headmaster_signature, 40, 104, 260)
        : null;
@endphp

<div class="signature-wrapper">
    <table class="signature-table">
        <tr>
            <td style="width: 45%;">
                <div>Mengetahui,</div>
                <div>Wali Kelas {{ $teachingClass->class_name }}</div>
                @if($wkSigBox)
                    <div class="sig-space-filled">
                        <img src="{{ $wkSigBox['data_uri'] }}"
                             class="sig-img"
                             style="width: {{ $wkSigBox['width'] }}px; height: {{ $wkSigBox['height'] }}px; margin-top: {{ $wkSigBox['margin_top'] }}px; margin-bottom: {{ $wkSigBox['margin_bottom'] }}px;"
                             alt="TTD Wali Kelas">
                    </div>
                @else
                    <div class="sig-space-empty"></div>
                @endif
                <div class="sig-name">{{ $teachingClass->homeroom_teacher_name ?: '........................................' }}</div>
                <div>NIP. {{ $teachingClass->homeroom_teacher_nip ?: '-' }}</div>
            </td>
            <td style="width: 10%;"></td>
            <td style="width: 45%;">
                <div>{{ $options['titimangsa'] }}</div>
                <div>Guru Mata Pelajaran</div>
                @if($guruSigBox)
                    <div class="sig-space-filled">
                        <img src="{{ $guruSigBox['data_uri'] }}"
                             class="sig-img"
                             style="width: {{ $guruSigBox['width'] }}px; height: {{ $guruSigBox['height'] }}px; margin-top: {{ $guruSigBox['margin_top'] }}px; margin-bottom: {{ $guruSigBox['margin_bottom'] }}px;"
                             alt="TTD Guru">
                    </div>
                @else
                    <div class="sig-space-empty"></div>
                @endif
                <div class="sig-name">{{ $teachingClass->teacher->name ?? '........................................' }}</div>
                <div>NIP. {{ $teachingClass->teacher->nip ?: '-' }}</div>
            </td>
        </tr>
        @if($options['show_headmaster'] ?? false)
        <tr>
            <td colspan="3" style="padding-top: 16px;">
                <div>Mengetahui / Mengesahkan,</div>
                <div>Kepala {{ $school->school_name }}</div>
                @if($hmSigBox)
                    <div class="sig-space-filled">
                        <img src="{{ $hmSigBox['data_uri'] }}"
                             class="sig-img"
                             style="width: {{ $hmSigBox['width'] }}px; height: {{ $hmSigBox['height'] }}px; margin-top: {{ $hmSigBox['margin_top'] }}px; margin-bottom: {{ $hmSigBox['margin_bottom'] }}px;"
                             alt="TTD Kepala Sekolah">
                    </div>
                @else
                    <div class="sig-space-empty"></div>
                @endif
                <div class="sig-name">{{ $school->headmaster_name ?: '........................................' }}</div>
                <div>NIP. {{ $school->headmaster_nip ?: '-' }}</div>
            </td>
        </tr>
        @endif
    </table>
</div>
