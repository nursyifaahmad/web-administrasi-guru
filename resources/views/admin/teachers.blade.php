@extends('layouts.app')

@section('title', 'Data Master Guru')
@section('page_title', 'Data Master Guru & Tenaga Pendidik')
@section('page_subtitle', 'Pusat basis data tenaga pendidik SMPN 2 Lemahabang: NIP, nomor telepon, kode mapel, kode mengajar jadwal, dan tanda tangan digital (TTD).')

@section('content')
<div class="space-y-5" x-data="{
    createModal: false,
    editUser: null,
    quickSig: { open: false, user_id: null, user_name: '', signature_data: '', pad: null, sigData: '' },
    scheduleTeachers: @js($scheduleTeachers ?? []),
    allSubjects: @js($subjects->map(fn($s) => ['id' => $s->id, 'code' => $s->code, 'name' => $s->name])->values()->all()),
    selectedScheduleIdx: '',
    createPad: null,
    editPad: null,
    createSigData: '',
    editSigData: '',
    form: {
        name: '',
        nip: '',
        role: 'guru',
        email: '',
        phone: '',
        password: 'password123',
        schedule_codes: '',
        teacher_subjects: [
            { subject_id: '', teaching_code: '' }
        ],
    },
    activeScheduleInfo: null,
    resetCreateForm() {
        this.selectedScheduleIdx = '';
        this.activeScheduleInfo = null;
        this.createSigData = '';
        if (this.createPad) this.createPad.clear();
        this.form = {
            name: '',
            nip: '',
            role: 'guru',
            email: '',
            phone: '',
            password: 'password123',
            schedule_codes: '',
            teacher_subjects: [
                { subject_id: '', teaching_code: '' }
            ],
        };
    },
    openCreateModal() {
        this.resetCreateForm();
        this.createModal = true;
        this.$nextTick(() => {
            if (this.$refs.createCanvas && !this.createPad) {
                this.createPad = window.initSignatureCanvas(this.$refs.createCanvas, (data) => {
                    this.createSigData = data;
                });
            } else if (this.createPad) {
                this.createPad.clear();
            }
        });
    },
    addSubjectRow(target) {
        target.push({ subject_id: '', teaching_code: '' });
    },
    removeSubjectRow(target, index) {
        if (target.length > 1) {
            target.splice(index, 1);
        } else {
            target[0].subject_id = '';
            target[0].teaching_code = '';
        }
    },
    onSelectSchedule(idx) {
        this.selectedScheduleIdx = idx;
        if (idx === '' || !this.scheduleTeachers[idx]) {
            this.activeScheduleInfo = null;
            this.form.schedule_codes = '';
            return;
        }
        const st = this.scheduleTeachers[idx];
        this.activeScheduleInfo = st;
        this.form.name = st.name;
        this.form.role = 'guru';
        this.form.schedule_codes = (st.codes || []).join(',');
        if (!this.form.email && st.suggested_email) {
            this.form.email = st.suggested_email;
        }

        // Petakan otomatis mapel jadwal ke baris teacher_subjects
        const rows = [];
        if (st.subjects && st.subjects.length > 0) {
            st.subjects.forEach((subjStr, sIdx) => {
                const cleanSubj = subjStr.toLowerCase().trim();
                const matched = this.allSubjects.find(s => 
                    cleanSubj.includes(s.name.toLowerCase()) || 
                    s.name.toLowerCase().includes(cleanSubj) || 
                    cleanSubj.includes(s.code.toLowerCase())
                );
                const codeForThis = (st.codes && st.codes[sIdx]) ? st.codes[sIdx] : (st.codes && st.codes[0] ? st.codes[0] : '');
                rows.push({
                    subject_id: matched ? matched.id : '',
                    teaching_code: codeForThis
                });
            });
        }
        if (rows.length > 0) {
            this.form.teacher_subjects = rows;
        } else if (st.codes && st.codes.length > 0) {
            this.form.teacher_subjects = [
                { subject_id: '', teaching_code: st.codes[0] }
            ];
        }
    },
    openEditModal(userData) {
        this.editUser = userData;
        this.editSigData = '';
        this.$nextTick(() => {
            if (this.$refs.editCanvas) {
                this.editPad = window.initSignatureCanvas(this.$refs.editCanvas, (data) => {
                    this.editSigData = data;
                });
            }
        });
    },
    openQuickSigModal(userId, userName, currentSig) {
        this.quickSig.user_id = userId;
        this.quickSig.user_name = userName;
        this.quickSig.signature_data = currentSig || '';
        this.quickSig.sigData = '';
        this.quickSig.open = true;
        this.$nextTick(() => {
            if (this.$refs.quickCanvas) {
                this.quickSig.pad = window.initSignatureCanvas(this.$refs.quickCanvas, (data) => {
                    this.quickSig.sigData = data;
                });
                if (this.quickSig.pad) this.quickSig.pad.clear();
            }
        });
    }
}">
    {{-- Toolbar Atas: Pencarian & Tambah Master Guru --}}
    <div class="bg-white rounded-2xl border border-slate-200 p-4 shadow-2xs flex flex-wrap items-center justify-between gap-3">
        <form method="GET" action="{{ route('admin.teachers.index') }}" class="flex items-center gap-2 flex-1 max-w-md">
            <input type="text" name="q" value="{{ $search }}" placeholder="Cari nama guru, NIP, telepon, atau kode..."
                   class="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-xs focus:ring-2 focus:ring-emerald-500">
            <button type="submit" class="px-4 py-2 rounded-xl bg-slate-900 text-white text-xs font-bold hover:bg-slate-800 cursor-pointer">Cari</button>
            @if($search)
                <a href="{{ route('admin.teachers.index') }}" class="px-3 py-2 rounded-xl bg-slate-100 text-slate-600 text-xs font-semibold hover:bg-slate-200">Reset</a>
            @endif
        </form>

        <div class="flex items-center gap-2">
            <button type="button" @click="openCreateModal()"
                    class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-emerald-600 text-white text-xs font-bold hover:bg-emerald-700 shadow-2xs transition cursor-pointer">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                <span>+ Tambah Master Guru</span>
            </button>
        </div>
    </div>

    {{-- Tabel Data Master Guru --}}
    <div class="bg-white rounded-2xl border border-slate-200 shadow-2xs overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-xs text-left border-collapse">
                <thead class="bg-slate-50 text-slate-700 uppercase text-[11px] font-bold border-b border-slate-200">
                    <tr>
                        <th class="px-3 py-3 w-12 text-center border-r border-slate-200">No</th>
                        <th class="px-4 py-3 min-w-[200px] border-r border-slate-200">Nama Lengkap</th>
                        <th class="px-3 py-3 w-36 border-r border-slate-200">NIP / NUPTK</th>
                        <th class="px-3 py-3 w-36 border-r border-slate-200">Nomor Telepon</th>
                        <th class="px-3 py-3 min-w-[130px] border-r border-slate-200">Kode Mapel</th>
                        <th class="px-3 py-3 w-32 border-r border-slate-200 text-center">Kode Mengajar</th>
                        <th class="px-3 py-3 w-28 text-center border-r border-slate-200">TTD Digital</th>
                        <th class="px-3 py-3 w-24 text-center border-r border-slate-200">Status</th>
                        <th class="px-4 py-3 w-32 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($users as $u)
                        @php
                            $userTeacherSubjects = $u->teacherSubjects ?? collect();
                            $cleanPhone = preg_replace('/[^0-9]/', '', (string)$u->phone);
                            if (str_starts_with($cleanPhone, '0')) {
                                $waPhone = '62' . substr($cleanPhone, 1);
                            } else {
                                $waPhone = $cleanPhone;
                            }
                        @endphp
                        <tr class="hover:bg-slate-50/80 transition">
                            {{-- 1. Nomor --}}
                            <td class="px-3 py-3 text-center font-bold text-slate-500 border-r border-slate-100">
                                {{ $loop->iteration }}
                            </td>

                            {{-- 2. Nama Lengkap --}}
                            <td class="px-4 py-3 border-r border-slate-100">
                                <div class="flex items-center gap-1.5 flex-wrap">
                                    <span class="font-bold text-slate-900 text-xs">{{ $u->name }}</span>
                                    <span class="px-1.5 py-0.5 rounded text-[10px] font-extrabold {{ $u->isAdmin() ? 'bg-purple-50 text-purple-700 border border-purple-200' : 'bg-emerald-50 text-emerald-700 border border-emerald-200' }}">
                                        {{ strtoupper($u->role) }}
                                    </span>
                                </div>
                                <div class="text-[11px] text-slate-500 mt-0.5 font-mono">{{ $u->email }}</div>
                            </td>

                            {{-- 3. NIP --}}
                            <td class="px-3 py-3 font-mono text-slate-700 border-r border-slate-100 whitespace-nowrap">
                                {{ $u->nip ?: '-' }}
                            </td>

                            {{-- 4. Nomor Telepon --}}
                            <td class="px-3 py-3 border-r border-slate-100 whitespace-nowrap">
                                @if(!empty($u->phone))
                                    <div class="flex items-center gap-1.5">
                                        <a href="https://wa.me/{{ $waPhone }}" target="_blank" title="Kirim Pesan WhatsApp"
                                           class="inline-flex items-center gap-1 font-semibold text-emerald-700 hover:text-emerald-800 hover:underline">
                                            <span>📱 {{ $u->phone }}</span>
                                        </a>
                                    </div>
                                @else
                                    <span class="text-slate-400">-</span>
                                @endif
                            </td>

                            {{-- 5. Kode Mapel (Multi-Mapel dari tabel subjects) --}}
                            <td class="px-3 py-3 border-r border-slate-100">
                                @if($userTeacherSubjects->isNotEmpty())
                                    <div class="flex flex-wrap gap-1">
                                        @foreach($userTeacherSubjects as $ts)
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-indigo-50 text-indigo-700 border border-indigo-200 font-extrabold text-[10px]"
                                                  title="{{ $ts->subject?->name }}">
                                                {{ $ts->subject?->code ?: '-' }}
                                            </span>
                                        @endforeach
                                    </div>
                                    <div class="text-[10px] text-slate-500 truncate max-w-[170px] mt-0.5" title="{{ $userTeacherSubjects->map(fn($t) => $t->subject?->name)->filter()->join(', ') }}">
                                        {{ $userTeacherSubjects->map(fn($t) => $t->subject?->name)->filter()->join(', ') }}
                                    </div>
                                @elseif(!empty($u->default_subject))
                                    <span class="px-2 py-0.5 rounded bg-slate-100 text-slate-700 font-medium text-[10px]">
                                        {{ $u->default_subject }}
                                    </span>
                                @else
                                    <span class="text-slate-400 italic text-[11px]">-</span>
                                @endif
                            </td>

                            {{-- 6. Kode Mengajar (Multi-Kode menyesuaikan mapel) --}}
                            <td class="px-3 py-3 border-r border-slate-100 text-center">
                                @php
                                    $teachingCodes = $userTeacherSubjects->pluck('teaching_code')->filter()->unique()->values();
                                @endphp
                                @if($teachingCodes->isNotEmpty())
                                    <div class="flex flex-wrap items-center justify-center gap-1">
                                        @foreach($teachingCodes as $tc)
                                            <span class="px-2 py-0.5 rounded-md bg-amber-50 text-amber-800 border border-amber-200 font-extrabold text-[11px]"
                                                  title="Kode Mengajar di Master Jadwal">
                                                {{ $tc }}
                                            </span>
                                        @endforeach
                                    </div>
                                @else
                                    <span class="text-slate-400">-</span>
                                @endif
                            </td>

                            {{-- 7. TTD Digital --}}
                            <td class="px-3 py-3 border-r border-slate-100 text-center">
                                @if(!empty($u->signature_data))
                                    <div class="inline-block relative group">
                                        <button type="button" @click="openQuickSigModal({{ $u->id }}, @js($u->name), @js($u->signature_data))"
                                                class="p-1 rounded-lg border border-slate-200 bg-slate-50 hover:bg-emerald-50 hover:border-emerald-300 transition cursor-pointer"
                                                title="Klik untuk melihat / perbarui TTD">
                                            <img src="{{ $u->signature_data }}" alt="TTD {{ $u->name }}" class="max-h-8 max-w-[70px] object-contain mx-auto">
                                        </button>
                                    </div>
                                @else
                                    <button type="button" @click="openQuickSigModal({{ $u->id }}, @js($u->name), null)"
                                            class="inline-flex items-center gap-1 px-2 py-1 rounded-lg border border-dashed border-slate-300 text-slate-400 hover:text-emerald-700 hover:border-emerald-300 hover:bg-emerald-50 text-[10px] font-semibold transition cursor-pointer"
                                            title="Belum ada tanda tangan digital. Klik untuk unggah atau gambar.">
                                        <span>+ Upload TTD</span>
                                    </button>
                                @endif
                            </td>

                            {{-- 8. Status Akun --}}
                            <td class="px-3 py-3 text-center border-r border-slate-100">
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold {{ $u->is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' }}">
                                    {{ $u->is_active ? 'Aktif' : 'Nonaktif' }}
                                </span>
                            </td>

                            {{-- 9. Aksi --}}
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <div class="inline-flex items-center gap-1">
                                    <button type="button"
                                            @click="openEditModal(@js([
                                                'id' => $u->id,
                                                'name' => $u->name,
                                                'email' => $u->email,
                                                'nip' => $u->nip,
                                                'role' => $u->role,
                                                'phone' => $u->phone,
                                                'default_subject' => $u->default_subject,
                                                'signature_data' => $u->signature_data,
                                                'is_active' => (bool)$u->is_active,
                                                'update_url' => route('admin.teachers.update', $u),
                                                'teacher_subjects' => $userTeacherSubjects->map(fn($ts) => [
                                                    'subject_id' => $ts->subject_id,
                                                    'teaching_code' => $ts->teaching_code ?? '',
                                                ])->values()->all(),
                                            ]))"
                                            class="px-2.5 py-1 rounded-lg bg-slate-100 text-slate-700 font-semibold hover:bg-slate-200 cursor-pointer">
                                        Edit
                                    </button>

                                    @if($u->id !== auth()->id())
                                        <form method="POST" action="{{ route('admin.teachers.toggle', $u) }}" class="inline">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit"
                                                    title="{{ $u->is_active ? 'Nonaktifkan akun guru' : 'Aktifkan akun guru' }}"
                                                    class="px-2 py-1 rounded-lg font-semibold {{ $u->is_active ? 'bg-amber-50 text-amber-800 hover:bg-amber-100' : 'bg-emerald-50 text-emerald-700 hover:bg-emerald-100' }}">
                                                {{ $u->is_active ? 'Off' : 'On' }}
                                            </button>
                                        </form>
                                        <form method="POST" action="{{ route('admin.teachers.destroy', $u) }}" class="inline"
                                              onsubmit="return confirm('Hapus data master guru {{ $u->name }} beserta akun dan seluruh buku kelasnya?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="px-2 py-1 rounded-lg bg-rose-50 text-rose-700 font-semibold hover:bg-rose-100 cursor-pointer" title="Hapus Master Guru">
                                                &times;
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-5 py-10 text-center text-slate-500">
                                Tidak ada data guru yang cocok dengan pencarian.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- MODAL TAMBAH MASTER GURU BARU --}}
    <div x-show="createModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
        <div @click.away="createModal = false" class="bg-white rounded-2xl border border-slate-200 shadow-xl max-w-2xl w-full max-h-[92vh] flex flex-col overflow-hidden">
            <div class="px-5 py-4 bg-slate-900 text-white flex items-center justify-between shrink-0">
                <div>
                    <h3 class="text-sm font-bold">Tambah Data Master Guru Baru</h3>
                    <p class="text-[11px] text-slate-300">Lengkapi data pokok, nomor telepon, kode mapel, kode mengajar, dan tanda tangan digital.</p>
                </div>
                <button type="button" @click="createModal = false" class="text-slate-400 hover:text-white text-xl cursor-pointer">&times;</button>
            </div>

            <form method="POST" action="{{ route('admin.teachers.store') }}" enctype="multipart/form-data" class="p-5 space-y-4 text-xs overflow-y-auto flex-1">
                @csrf
                <input type="hidden" name="schedule_codes" x-model="form.schedule_codes">
                <input type="hidden" name="signature_data" :value="createSigData">

                {{-- Bantuan Auto-Fill dari Master Jadwal Sekolah --}}
                <div class="p-3 bg-emerald-50/70 border border-emerald-200 rounded-xl space-y-1.5">
                    <label class="block font-bold text-emerald-950 flex items-center gap-1.5">
                        <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        <span>Pilih Guru dari Master Jadwal Sekolah (Otomatis)</span>
                    </label>
                    <select x-model="selectedScheduleIdx" @change="onSelectSchedule($event.target.value)"
                            class="w-full rounded-xl border border-emerald-300 bg-white px-3 py-2 text-xs font-semibold text-slate-800 focus:ring-2 focus:ring-emerald-500 shadow-2xs">
                        <option value="">-- Ketik Manual atau Pilih Guru dari Master Jadwal --</option>
                        @foreach($scheduleTeachers as $idx => $st)
                            <option value="{{ $idx }}" {{ $st['has_account'] ? 'disabled class="text-slate-400 bg-slate-100"' : '' }}>
                                [Kode {{ $st['codes_label'] }}] {{ $st['name'] }} — {{ $st['subjects_label'] }}
                                @if($st['has_account']) (Sudah Ada Akun) @endif
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Bagian 1: Data Identitas Pokok Guru --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-1">
                    <div class="sm:col-span-2">
                        <label class="block font-bold text-slate-700 mb-1">Nama Lengkap &amp; Gelar *</label>
                        <input type="text" name="name" x-model="form.name" required placeholder="Contoh: Budi Santoso, S.Pd., Gr."
                               class="w-full rounded-xl border border-slate-300 px-3 py-2 font-bold text-slate-800 focus:ring-2 focus:ring-emerald-500">
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">NIP / NUPTK</label>
                        <input type="text" name="nip" x-model="form.nip" placeholder="Contoh: 19900412 201903 1 008"
                               class="w-full rounded-xl border border-slate-300 px-3 py-2 font-mono focus:ring-2 focus:ring-emerald-500">
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Nomor Telepon / WhatsApp</label>
                        <input type="text" name="phone" x-model="form.phone" placeholder="Contoh: 081234567890"
                               class="w-full rounded-xl border border-slate-300 px-3 py-2 focus:ring-2 focus:ring-emerald-500">
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Email Login *</label>
                        <input type="email" name="email" x-model="form.email" required placeholder="budi@smpn2lemahabang.sch.id"
                               class="w-full rounded-xl border border-slate-300 px-3 py-2 focus:ring-2 focus:ring-emerald-500">
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Peran Akses *</label>
                        <select name="role" x-model="form.role" class="w-full rounded-xl border border-slate-300 px-3 py-2 focus:ring-2 focus:ring-emerald-500 font-semibold">
                            <option value="guru">Guru Mata Pelajaran</option>
                            <option value="admin">Administrator / Wakasek</option>
                        </select>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block font-bold text-slate-700 mb-1">Kata Sandi Awal Akun *</label>
                        <input type="text" name="password" x-model="form.password" required
                               class="w-full rounded-xl border border-slate-300 px-3 py-2 font-mono focus:ring-2 focus:ring-emerald-500">
                    </div>
                </div>

                {{-- Bagian 2: Mata Pelajaran & Kode Mengajar (Multi-Mapel) --}}
                <div class="p-4 bg-slate-50 border border-slate-200 rounded-2xl space-y-3">
                    <div class="flex items-center justify-between">
                        <div>
                            <span class="block font-bold text-slate-800 text-xs">Mata Pelajaran yang Diampu &amp; Kode Mengajar</span>
                            <span class="block text-[11px] text-slate-500">Satu guru dapat mengampu lebih dari satu mapel dengan kode mengajar masing-masing.</span>
                        </div>
                        <button type="button" @click="addSubjectRow(form.teacher_subjects)"
                                class="px-2.5 py-1 rounded-lg bg-indigo-50 hover:bg-indigo-100 text-indigo-700 border border-indigo-200 font-bold text-[11px] cursor-pointer">
                            + Tambah Mapel
                        </button>
                    </div>

                    <div class="space-y-2">
                        <template x-for="(row, rIdx) in form.teacher_subjects" :key="'create-ts-'+rIdx">
                            <div class="flex items-center gap-2 p-2 bg-white rounded-xl border border-slate-200">
                                <div class="flex-1">
                                    <label class="block text-[10px] font-bold text-slate-600 mb-0.5">Mata Pelajaran (Dari tabel Mapel &amp; KKTP):</label>
                                    <select :name="'teacher_subjects['+rIdx+'][subject_id]'" x-model="row.subject_id"
                                            class="w-full rounded-lg border border-slate-300 px-2.5 py-1.5 text-xs font-semibold text-slate-800 bg-white">
                                        <option value="">-- Pilih Mata Pelajaran --</option>
                                        <template x-for="sb in allSubjects" :key="sb.id">
                                            <option :value="sb.id" x-text="'[' + sb.code + '] ' + sb.name"></option>
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
                                    <button type="button" @click="removeSubjectRow(form.teacher_subjects, rIdx)"
                                            class="p-1.5 rounded-lg text-rose-500 hover:text-rose-700 hover:bg-rose-50 cursor-pointer"
                                            title="Hapus baris mapel ini">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                {{-- Bagian 3: Tanda Tangan Digital (TTD) --}}
                <div class="p-4 bg-slate-50 border border-slate-200 rounded-2xl space-y-3">
                    <div class="flex items-center justify-between">
                        <div>
                            <span class="block font-bold text-slate-800 text-xs">Tanda Tangan Digital (TTD Guru)</span>
                            <span class="block text-[11px] text-slate-500">Dapat digoreskan di kanvas atau diunggah dari berkas scan foto. Latar belakang putih otomatis dibersihkan transparan.</span>
                        </div>
                        <button type="button" @click="if (createPad) createPad.clear(); createSigData = '';"
                                class="text-[11px] text-rose-600 font-semibold hover:underline cursor-pointer">
                            Bersihkan Kanvas
                        </button>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3 items-start">
                        <div>
                            <span class="block text-[11px] font-semibold text-slate-600 mb-1">Goreskan TTD di Kanvas:</span>
                            <canvas x-ref="createCanvas" width="340" height="120"
                                    class="w-full h-28 rounded-xl border-2 border-dashed border-slate-300 bg-white cursor-crosshair touch-none"></canvas>
                        </div>
                        <div>
                            <span class="block text-[11px] font-semibold text-slate-600 mb-1">Atau Unggah Berkas Scan TTD (PNG/JPG):</span>
                            <input type="file" name="signature_file" accept="image/*"
                                   class="w-full text-xs text-slate-600 file:mr-2 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:bg-emerald-50 file:text-emerald-700 file:font-semibold hover:file:bg-emerald-100">
                            <p class="text-[10px] text-slate-500 mt-1.5 leading-relaxed">
                                Sistem secara cerdas akan memotong margin kosong (*auto-crop*) dan menghapus latar putih sehingga hasil cetak pada dokumen absensi &amp; jurnal tampak seperti tanda tangan asli basah.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="pt-3 flex justify-end gap-2 border-t border-slate-100 shrink-0">
                    <button type="button" @click="createModal = false" class="px-4 py-2 rounded-xl border border-slate-300 font-semibold hover:bg-slate-50 cursor-pointer">Batal</button>
                    <button type="submit" class="px-5 py-2 rounded-xl bg-emerald-600 text-white font-bold hover:bg-emerald-700 shadow-2xs cursor-pointer">Simpan Data Master Guru</button>
                </div>
            </form>
        </div>
    </div>

    {{-- MODAL EDIT DATA MASTER GURU --}}
    <div x-show="editUser !== null" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
        <template x-if="editUser">
            <div @click.away="editUser = null" class="bg-white rounded-2xl border border-slate-200 shadow-xl max-w-2xl w-full max-h-[92vh] flex flex-col overflow-hidden">
                <div class="px-5 py-4 bg-slate-900 text-white flex items-center justify-between shrink-0">
                    <div>
                        <h3 class="text-sm font-bold">Edit Master Guru: <span x-text="editUser.name"></span></h3>
                        <p class="text-[11px] text-slate-300">Perbarui data pokok, kontak, penugasan mapel, kode mengajar, dan TTD.</p>
                    </div>
                    <button type="button" @click="editUser = null" class="text-slate-400 hover:text-white text-xl cursor-pointer">&times;</button>
                </div>

                <form method="POST" :action="editUser.update_url" enctype="multipart/form-data" class="p-5 space-y-4 text-xs overflow-y-auto flex-1">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="signature_data" :value="editSigData">

                    {{-- Data Pokok --}}
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div class="sm:col-span-2">
                            <label class="block font-bold text-slate-700 mb-1">Nama Lengkap &amp; Gelar *</label>
                            <input type="text" name="name" x-model="editUser.name" required class="w-full rounded-xl border border-slate-300 px-3 py-2 font-bold text-slate-800">
                        </div>
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">NIP / NUPTK</label>
                            <input type="text" name="nip" x-model="editUser.nip" class="w-full rounded-xl border border-slate-300 px-3 py-2 font-mono">
                        </div>
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Nomor Telepon / WA</label>
                            <input type="text" name="phone" x-model="editUser.phone" class="w-full rounded-xl border border-slate-300 px-3 py-2">
                        </div>
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Email Login *</label>
                            <input type="email" name="email" x-model="editUser.email" required class="w-full rounded-xl border border-slate-300 px-3 py-2">
                        </div>
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Peran Akses *</label>
                            <select name="role" x-model="editUser.role" class="w-full rounded-xl border border-slate-300 px-3 py-2 font-semibold">
                                <option value="guru">Guru Mata Pelajaran</option>
                                <option value="admin">Administrator / Wakasek</option>
                            </select>
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block font-bold text-slate-700 mb-1">Reset Kata Sandi (Opsional)</label>
                            <input type="text" name="password" placeholder="Kosongkan bila tidak ingin mengubah kata sandi"
                                   class="w-full rounded-xl border border-slate-300 px-3 py-2 font-mono">
                        </div>
                    </div>

                    {{-- Penugasan Mapel & Kode Mengajar --}}
                    <div class="p-4 bg-slate-50 border border-slate-200 rounded-2xl space-y-3">
                        <div class="flex items-center justify-between">
                            <div>
                                <span class="block font-bold text-slate-800 text-xs">Mata Pelajaran &amp; Kode Mengajar</span>
                                <span class="block text-[11px] text-slate-500">Satu guru dapat memiliki lebih dari satu kode mengajar dan kode mapel.</span>
                            </div>
                            <button type="button" @click="addSubjectRow(editUser.teacher_subjects)"
                                    class="px-2.5 py-1 rounded-lg bg-indigo-50 hover:bg-indigo-100 text-indigo-700 border border-indigo-200 font-bold text-[11px] cursor-pointer">
                                + Tambah Mapel
                            </button>
                        </div>

                        <div class="space-y-2">
                            <template x-for="(row, rIdx) in editUser.teacher_subjects" :key="'edit-ts-'+rIdx">
                                <div class="flex items-center gap-2 p-2 bg-white rounded-xl border border-slate-200">
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
                                        <button type="button" @click="removeSubjectRow(editUser.teacher_subjects, rIdx)"
                                                class="p-1.5 rounded-lg text-rose-500 hover:text-rose-700 hover:bg-rose-50 cursor-pointer"
                                                title="Hapus baris mapel ini">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        </button>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>

                    {{-- Pengelolaan Tanda Tangan Digital (TTD) --}}
                    <div class="p-4 bg-slate-50 border border-slate-200 rounded-2xl space-y-3">
                        <div class="flex items-center justify-between">
                            <div>
                                <span class="block font-bold text-slate-800 text-xs">Tanda Tangan Digital (TTD)</span>
                                <span class="block text-[11px] text-slate-500">Pratinjau tanda tangan saat ini dan opsi pembaruan.</span>
                            </div>
                            <button type="button" @click="if (editPad) editPad.clear(); editSigData = '';"
                                    class="text-[11px] text-rose-600 font-semibold hover:underline cursor-pointer">
                                Bersihkan Kanvas
                            </button>
                        </div>

                        <template x-if="editUser.signature_data">
                            <div class="p-3 rounded-xl bg-white border border-slate-200 flex items-center justify-between gap-4">
                                <div class="flex items-center gap-3">
                                    <span class="text-[11px] font-bold text-slate-600">TTD Tersimpan Saat Ini:</span>
                                    <div class="p-1 bg-slate-50 border border-slate-200 rounded-lg">
                                        <img :src="editUser.signature_data" alt="TTD" class="max-h-12 max-w-[100px] object-contain">
                                    </div>
                                </div>
                                <label class="flex items-center gap-1.5 text-xs text-rose-600 font-bold cursor-pointer">
                                    <input type="checkbox" name="remove_signature" value="1" class="rounded border-slate-300 text-rose-600">
                                    <span>Hapus TTD Ini</span>
                                </label>
                            </div>
                        </template>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 items-start">
                            <div>
                                <span class="block text-[11px] font-semibold text-slate-600 mb-1">Goreskan TTD Baru di Kanvas:</span>
                                <canvas x-ref="editCanvas" width="340" height="120"
                                        class="w-full h-28 rounded-xl border-2 border-dashed border-slate-300 bg-white cursor-crosshair touch-none"></canvas>
                            </div>
                            <div>
                                <span class="block text-[11px] font-semibold text-slate-600 mb-1">Atau Unggah Berkas Gambar TTD (PNG/JPG):</span>
                                <input type="file" name="signature_file" accept="image/*"
                                       class="w-full text-xs text-slate-600 file:mr-2 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:bg-emerald-50 file:text-emerald-700 file:font-semibold hover:file:bg-emerald-100">
                                <p class="text-[10px] text-slate-500 mt-1.5 leading-relaxed">
                                    Mengunggah berkas baru atau menggambar di kanvas akan menggantikan tanda tangan digital sebelumnya.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="pt-3 flex justify-end gap-2 border-t border-slate-100 shrink-0">
                        <button type="button" @click="editUser = null" class="px-4 py-2 rounded-xl border border-slate-300 font-semibold hover:bg-slate-50 cursor-pointer">Batal</button>
                        <button type="submit" class="px-5 py-2 rounded-xl bg-emerald-600 text-white font-bold hover:bg-emerald-700 shadow-2xs cursor-pointer">Simpan Perubahan</button>
                    </div>
                </form>
            </div>
        </template>
    </div>

    {{-- MODAL CEPAT KELOLA TTD (QUICK SIGNATURE MODAL) --}}
    <div x-show="quickSig.open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
        <div @click.away="quickSig.open = false" class="bg-white rounded-2xl border border-slate-200 shadow-xl max-w-md w-full overflow-hidden">
            <div class="px-5 py-4 bg-slate-900 text-white flex items-center justify-between shrink-0">
                <div>
                    <h3 class="text-sm font-bold">Kelola Tanda Tangan Digital</h3>
                    <p class="text-[11px] text-slate-300" x-text="'Guru: ' + quickSig.user_name"></p>
                </div>
                <button type="button" @click="quickSig.open = false" class="text-slate-400 hover:text-white text-xl cursor-pointer">&times;</button>
            </div>

            <form method="POST" :action="'/admin/guru/' + quickSig.user_id + '/signature'" enctype="multipart/form-data" class="p-5 space-y-4 text-xs">
                @csrf
                @method('PATCH')
                <input type="hidden" name="signature_data" :value="quickSig.sigData">

                <template x-if="quickSig.signature_data">
                    <div class="p-3 rounded-xl bg-slate-50 border border-slate-200 space-y-2">
                        <div class="text-[11px] font-bold text-slate-700">Tanda Tangan Saat Ini:</div>
                        <div class="p-2 bg-white rounded-lg border border-slate-200 flex items-center justify-center">
                            <img :src="quickSig.signature_data" alt="TTD" class="max-h-16 object-contain">
                        </div>
                        <label class="flex items-center gap-1.5 text-xs text-rose-600 font-bold cursor-pointer pt-1">
                            <input type="checkbox" name="remove_signature" value="1" class="rounded border-slate-300 text-rose-600">
                            <span>Hapus Tanda Tangan Ini</span>
                        </label>
                    </div>
                </template>

                <div>
                    <div class="flex items-center justify-between mb-1">
                        <label class="block font-bold text-slate-700">Goreskan TTD Baru:</label>
                        <button type="button" @click="if (quickSig.pad) quickSig.pad.clear(); quickSig.sigData = '';"
                                class="text-[11px] text-rose-600 font-semibold hover:underline cursor-pointer">
                            Bersihkan Kanvas
                        </button>
                    </div>
                    <canvas x-ref="quickCanvas" width="360" height="120"
                            class="w-full h-28 rounded-xl border-2 border-dashed border-slate-300 bg-slate-50 cursor-crosshair touch-none"></canvas>
                </div>

                <div>
                    <label class="block font-bold text-slate-700 mb-1">Atau Unggah File Gambar TTD (PNG/JPG):</label>
                    <input type="file" name="signature_file" accept="image/*"
                           class="w-full text-xs text-slate-600 file:mr-2 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:bg-emerald-50 file:text-emerald-700 file:font-semibold hover:file:bg-emerald-100">
                </div>

                <div class="pt-3 flex justify-end gap-2 border-t border-slate-100">
                    <button type="button" @click="quickSig.open = false" class="px-4 py-2 rounded-xl border border-slate-300 font-semibold hover:bg-slate-50 cursor-pointer">Batal</button>
                    <button type="submit" class="px-5 py-2 rounded-xl bg-emerald-600 text-white font-bold hover:bg-emerald-700 shadow-2xs cursor-pointer">Simpan TTD</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
