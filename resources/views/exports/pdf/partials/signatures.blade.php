<div class="signature-wrapper">
    <table class="signature-table">
        <tr>
            <td style="width: 45%;">
                <div>Mengetahui,</div>
                <div>Wali Kelas {{ $teachingClass->class_name }}</div>
                <div class="sig-space">
                    @if(($options['show_digital_signature'] ?? true) && !empty($teachingClass->homeroom_teacher_signature))
                        <img src="{{ $teachingClass->homeroom_teacher_signature }}" class="sig-img" alt="TTD Wali Kelas">
                    @endif
                </div>
                <div class="sig-name">{{ $teachingClass->homeroom_teacher_name ?: '........................................' }}</div>
                <div>NIP. {{ $teachingClass->homeroom_teacher_nip ?: '-' }}</div>
            </td>
            <td style="width: 10%;"></td>
            <td style="width: 45%;">
                <div>{{ $options['titimangsa'] }}</div>
                <div>Guru Mata Pelajaran</div>
                <div class="sig-space">
                    @if(($options['show_digital_signature'] ?? true) && !empty($teachingClass->teacher?->signature_data))
                        <img src="{{ $teachingClass->teacher->signature_data }}" class="sig-img" alt="TTD Guru">
                    @endif
                </div>
                <div class="sig-name">{{ $teachingClass->teacher->name ?? '........................................' }}</div>
                <div>NIP. {{ $teachingClass->teacher->nip ?: '-' }}</div>
            </td>
        </tr>
        @if($options['show_headmaster'] ?? false)
        <tr>
            <td colspan="3" style="padding-top: 14px;">
                <div>Mengetahui / Mengesahkan,</div>
                <div>Kepala {{ $school->school_name }}</div>
                <div class="sig-space">
                    @if(($options['show_digital_signature'] ?? true) && !empty($school->headmaster_signature))
                        <img src="{{ $school->headmaster_signature }}" class="sig-img" alt="TTD Kepala Sekolah">
                    @endif
                </div>
                <div class="sig-name">{{ $school->headmaster_name ?: '........................................' }}</div>
                <div>NIP. {{ $school->headmaster_nip ?: '-' }}</div>
            </td>
        </tr>
        @endif
    </table>
</div>
