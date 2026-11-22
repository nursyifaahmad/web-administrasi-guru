{{-- Shared Form Fields: Mata Pelajaran & Kode Mengajar + Penugasan Wali Kelas & Jadwal Piket --}}
<div class="p-4 bg-slate-50 border border-slate-200 rounded-2xl space-y-3">
    <div class="flex items-center justify-between">
        <div>
            <span class="block font-bold text-slate-800 text-xs">Mata Pelajaran yang Diampu &amp; Kode Mengajar</span>
            <span class="block text-[11px] text-slate-500">Kode mengajar bersifat unik ketat (1 kode hanya untuk 1 guru; 1 guru boleh &gt;1 kode jika beda mapel).</span>
        </div>
        <button type="button" @click="addSubjectRow({{ $prefix }}.teacher_subjects)"
                class="px-2.5 py-1 rounded-lg bg-indigo-50 hover:bg-indigo-100 text-indigo-700 border border-indigo-200 font-bold text-[11px] cursor-pointer">
            + Tambah Mapel
        </button>
    </div>

    <div class="space-y-2">
        <template x-for="(row, rIdx) in {{ $prefix }}.teacher_subjects" :key="'{{ $mode }}-ts-' + rIdx">
            <div class="p-2 bg-white rounded-xl border border-slate-200 space-y-1">
                <div class="flex items-center gap-2">
                    <div class="flex-1">
                        <label class="block text-[10px] font-bold text-slate-600 mb-0.5">Mata Pelajaran:</label>
                        <select :name="'teacher_subjects['+rIdx+'][subject_id]'" x-model="row.subject_id"
                                class="w-full rounded-lg border border-slate-300 px-2.5 py-1.5 text-xs font-semibold text-slate-800 bg-white">
                            <option value="">-- Pilih Mata Pelajaran --</option>
                            <template x-for="sb in allSubjects" :key="sb.id">
                                <option :value="sb.id" :selected="sb.id == row.subject_id" x-text="'[' + sb.code + '] ' + sb.name"></option>
                            </template>
                        </select>
                    </div>
                    <div class="w-36">
                        <label class="block text-[10px] font-bold text-slate-600 mb-0.5">Kode Mengajar:</label>
                        <input type="text" :name="'teacher_subjects['+rIdx+'][teaching_code]'" x-model="row.teaching_code"
                               placeholder="Misal: 14A, 7"
                               class="w-full rounded-lg border border-slate-300 px-2.5 py-1.5 text-xs font-bold text-center text-amber-900 bg-amber-50/50">
                    </div>
                    <div class="pt-4">
                        <button type="button" @click="removeSubjectRow({{ $prefix }}.teacher_subjects, rIdx)"
                                class="p-1.5 rounded-lg text-rose-500 hover:text-rose-700 hover:bg-rose-50 cursor-pointer"
                                title="Hapus baris mapel ini">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        </button>
                    </div>
                </div>
                <template x-if="findCodeConflict(row.teaching_code, {{ $currentUserId }})">
                    <div class="text-[10px] font-bold text-rose-600 bg-rose-50 border border-rose-200 rounded-lg px-2 py-1">
                        ⚠️ Kode <span x-text="normalizeCode(row.teaching_code)"></span> sudah dipakai oleh <span x-text="findCodeConflict(row.teaching_code, {{ $currentUserId }})"></span>.
                    </div>
                </template>
            </div>
        </template>
    </div>
</div>

{{-- Penugasan Terpusat: Wali Kelas & Jadwal Piket --}}
<div class="p-4 bg-emerald-50/50 border border-emerald-200 rounded-2xl space-y-3">
    <div>
        <span class="block font-bold text-slate-800 text-xs">Penugasan Wali Kelas &amp; Jadwal Piket (Master Data Tunggal)</span>
        <span class="block text-[11px] text-slate-500">Perubahan di sini otomatis tersinkron ke Master Rombel, Buku Kelas Guru, Master Jadwal, PDF, dan Excel.</span>
    </div>

    <div>
        <label class="block font-bold text-slate-700 mb-1">Wali Kelas ({{ $academicYear->full_label }})</label>
        <select name="homeroom_school_class_id" x-model="{{ $prefix }}.homeroom_school_class_id"
                class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-800">
            <option value="">-- Bukan Wali Kelas --</option>
            <template x-for="cls in allClasses" :key="'{{ $mode }}-wk-' + cls.id">
                <option :value="cls.id"
                        x-text="cls.name + (cls.homeroom_teacher_id && cls.homeroom_teacher_id != {{ $currentUserId }} ? ' (Sudah diampu: ' + cls.homeroom_teacher_name + ')' : (cls.homeroom_teacher_id == {{ $currentUserId }} ? ' (Kelas binaan saat ini)' : ' (Kosong / Tersedia)'))">
                </option>
            </template>
        </select>
        <p class="text-[10px] text-slate-500 mt-1">1 guru hanya dapat mengampu maksimal 1 rombel per tahun ajaran agar tidak bentrok.</p>
    </div>

    <div>
        <input type="hidden" name="has_piket_input" value="1">
        <label class="block font-bold text-slate-700 mb-1.5">Jadwal Guru Piket (Pilih 1 hari atau lebih bila perlu)</label>
        <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
            @foreach($piketDays as $dayName)
                @php
                    $existingPiketNames = collect($piketGrouped[$dayName] ?? [])->pluck('name')->values()->all();
                @endphp
                <label class="flex items-start gap-2 p-2 rounded-xl bg-white border border-slate-200 hover:border-emerald-400 cursor-pointer">
                    <input type="checkbox"
                           name="piket_days[]"
                           value="{{ $dayName }}"
                           x-model="{{ $prefix }}.piket_days"
                           class="mt-0.5 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                    <div class="min-w-0 leading-tight">
                        <span class="block font-bold text-[11px] text-slate-800">{{ $dayName }}</span>
                        @if(!empty($existingPiketNames))
                            <span class="block text-[9px] text-slate-500 truncate" title="{{ implode(', ', $existingPiketNames) }}">
                                Petugas: {{ count($existingPiketNames) }} guru
                            </span>
                        @else
                            <span class="block text-[9px] text-slate-400">Belum ada</span>
                        @endif
                    </div>
                </label>
            @endforeach
        </div>
    </div>
</div>
