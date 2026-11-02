@extends('layouts.app')

@section('title', 'Peserta Didik & Wali Kelas — ' . $teachingClass->class_name)
@section('page_title', 'Peserta Didik & Wali Kelas — Kelas ' . $teachingClass->class_name)
@section('page_subtitle', $teachingClass->subject->name . ' • ' . $teachingClass->academicYear->full_label)

@section('content')
@include('partials.class-workspace-tabs')

<div class="grid grid-cols-1 xl:grid-cols-12 gap-5"
     x-data="{
        addStudentModal: false,
        importModal: false,
        copyModal: false,
        editStudent: null,
        wkSigData: '',
        wkPad: null,
        init() {
            @if(auth()->user()->isAdmin())
            this.$nextTick(() => {
                if (this.$refs.wkCanvas) {
                    this.wkPad = window.initSignatureCanvas(this.$refs.wkCanvas, (d) => this.wkSigData = d);
                }
            });
            @endif
        }
     }">

    {{-- Kolom Kiri: Daftar Peserta Didik --}}
    <div class="xl:col-span-8 space-y-4">
        @if(!auth()->user()->isAdmin())
            <div class="p-4 rounded-2xl bg-indigo-50/70 border border-indigo-200 text-indigo-950 flex items-start gap-3">
                <div class="w-8 h-8 rounded-xl bg-indigo-600 text-white flex items-center justify-center shrink-0 text-sm font-bold shadow-xs">
                    ℹ
                </div>
                <div class="text-xs space-y-0.5">
                    <div class="font-bold text-slate-900">Data Peserta Didik Dikelola Terpusat</div>
                    <div class="text-slate-600 leading-relaxed">
                        Data rombongan belajar <strong>{{ $teachingClass->class_name }}</strong> dikelola secara terpusat oleh <strong>Administrator Sekolah</strong>. Daftar murid ini otomatis terhubung secara <em>real-time</em> dengan seluruh guru mata pelajaran lain yang mengajar di kelas yang sama.
                    </div>
                </div>
            </div>
        @endif

        <div class="bg-white rounded-2xl border border-slate-200 p-4 shadow-2xs flex flex-wrap items-center justify-between gap-2">
            <div>
                <h2 class="text-sm font-bold text-slate-900">Daftar Peserta Didik ({{ $students->count() }} Murid)</h2>
                <p class="text-xs text-slate-500">Digunakan otomatis pada menu Absensi dan Daftar Nilai kelas ini.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2 text-xs">
                @if(auth()->user()->isAdmin())
                    @if($teachingClass->school_class_id)
                        <a href="{{ route('admin.classes.show', $teachingClass->school_class_id) }}"
                           class="px-3.5 py-2 rounded-xl bg-purple-600 text-white font-bold hover:bg-purple-700 shadow-2xs">
                            Master Rombel Admin &rarr;
                        </a>
                    @endif
                    <button type="button" @click="addStudentModal = true"
                            class="px-3.5 py-2 rounded-xl bg-emerald-600 text-white font-bold hover:bg-emerald-700">
                        + Tambah Murid
                    </button>
                    <button type="button" @click="importModal = true"
                            class="px-3.5 py-2 rounded-xl bg-indigo-50 text-indigo-700 border border-indigo-200 font-bold hover:bg-indigo-100">
                        Import Excel Murid
                    </button>
                    @if($otherClasses->isNotEmpty())
                        <button type="button" @click="copyModal = true"
                                class="px-3 py-2 rounded-xl bg-slate-100 text-slate-700 font-semibold hover:bg-slate-200">
                            Salin dari Kelas Lain
                        </button>
                    @endif
                    @if($students->count() > 1)
                        <form method="POST" action="{{ route('classes.students.sort', $teachingClass) }}">
                            @csrf
                            <button type="submit" class="px-3 py-2 rounded-xl border border-slate-300 text-slate-700 font-semibold hover:bg-slate-50">
                                Urut Abjad A-Z
                            </button>
                        </form>
                    @endif
                @else
                    <span class="px-3 py-1.5 rounded-xl bg-slate-100 text-slate-600 font-semibold text-[11px]">
                        Mode Guru: Hanya Baca (Read-Only)
                    </span>
                @endif
            </div>
        </div>

        <div class="bg-white rounded-2xl border border-slate-200 shadow-2xs overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-xs text-left">
                    <thead class="bg-slate-50 text-slate-600 uppercase text-[11px] border-b border-slate-200">
                        <tr>
                            <th class="px-3 py-3 w-14 text-center">No</th>
                            <th class="px-3 py-3 w-28">NIS / NISN</th>
                            <th class="px-4 py-3">Nama Lengkap</th>
                            <th class="px-3 py-3 w-32">Nama Panggilan</th>
                            <th class="px-3 py-3 w-20 text-center">L / P</th>
                            @if(auth()->user()->isAdmin())
                                <th class="px-4 py-3 w-36 text-right">Aksi</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($students as $idx => $st)
                            <tr class="hover:bg-slate-50/70">
                                <td class="px-3 py-2.5 text-center font-bold text-slate-700">{{ $st->order_number }}</td>
                                <td class="px-3 py-2.5 font-mono text-slate-600">{{ $st->nis_nisn ?: '-' }}</td>
                                <td class="px-4 py-2.5 font-bold text-slate-900">{{ $st->full_name }}</td>
                                <td class="px-3 py-2.5 text-slate-700">{{ $st->nickname ?: '-' }}</td>
                                <td class="px-3 py-2.5 text-center">
                                    <span class="px-2 py-0.5 rounded-full font-bold text-[11px] {{ $st->gender === 'P' ? 'bg-pink-50 text-pink-700' : 'bg-sky-50 text-sky-700' }}">
                                        {{ $st->gender }}
                                    </span>
                                </td>
                                @if(auth()->user()->isAdmin())
                                    <td class="px-4 py-2.5 text-right">
                                        <div class="inline-flex items-center gap-1.5">
                                            <button type="button"
                                                    @click="editStudent = @js([
                                                        'order_number' => $st->order_number,
                                                        'nis_nisn' => $st->nis_nisn,
                                                        'full_name' => $st->full_name,
                                                        'nickname' => $st->nickname,
                                                        'gender' => $st->gender,
                                                        'update_url' => route('classes.students.update', [$teachingClass, $st]),
                                                    ])"
                                                    class="px-2.5 py-1 rounded-lg bg-slate-100 text-slate-700 font-semibold hover:bg-slate-200">
                                                Edit
                                            </button>
                                            <form method="POST" action="{{ route('classes.students.destroy', [$teachingClass, $st]) }}"
                                                  onsubmit="return confirm('Hapus murid {{ $st->full_name }}?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="px-2.5 py-1 rounded-lg bg-rose-50 text-rose-700 font-semibold hover:bg-rose-100">
                                                    Hapus
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ auth()->user()->isAdmin() ? 6 : 5 }}" class="px-5 py-10 text-center text-slate-500">
                                    @if(auth()->user()->isAdmin())
                                        Belum ada peserta didik di kelas ini. Klik <strong>+ Tambah Murid</strong> atau <strong>Import Excel Murid</strong>.
                                    @else
                                        Belum ada data peserta didik untuk rombel ini. Silakan hubungi <strong>Administrator Sekolah</strong> untuk menginput atau mengimpor data murid.
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Kolom Kanan: Identitas Kelas & Wali Kelas (+ NIP & TTD) --}}
    <div class="xl:col-span-4 space-y-4">
        <form method="POST" action="{{ route('classes.update', $teachingClass) }}" enctype="multipart/form-data"
              class="bg-white rounded-2xl border border-slate-200 shadow-2xs p-5 space-y-4 text-xs">
            @csrf
            @method('PUT')

            <div>
                <h3 class="text-sm font-bold text-slate-900">Pengaturan Kelas & Wali Kelas</h3>
                <p class="text-[11px] text-slate-500">Data Wali Kelas & NIP tercetak otomatis di bagian bawah PDF & Excel.</p>
            </div>

            <div class="space-y-3">
                <div>
                    <label class="block font-bold text-slate-700 mb-1">Mata Pelajaran *</label>
                    <select name="subject_id" required class="w-full rounded-xl border border-slate-300 px-3 py-2 font-semibold">
                        @foreach($subjects as $sub)
                            <option value="{{ $sub->id }}" @selected($sub->id === $teachingClass->subject_id)>{{ $sub->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block font-bold text-slate-700 mb-1">Jadwal Mengajar</label>
                    <input type="text" name="schedule_info" value="{{ $teachingClass->schedule_info }}"
                           placeholder="Misal: Senin (1-2) & Kamis (3-4)"
                           class="w-full rounded-xl border border-slate-300 px-3 py-2">
                </div>

                @if(auth()->user()->isAdmin())
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Tingkat *</label>
                            <select name="grade_level" required class="w-full rounded-xl border border-slate-300 px-3 py-2 font-bold">
                                @foreach($gradeLevels as $lvl)
                                    <option value="{{ $lvl }}" @selected($lvl === $teachingClass->grade_level)>Kelas {{ $lvl }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Nama Kelas *</label>
                            <input type="text" name="class_name" value="{{ $teachingClass->class_name }}" required
                                   class="w-full rounded-xl border border-slate-300 px-3 py-2 font-bold">
                        </div>
                    </div>

                    <div class="pt-3 border-t border-slate-100 space-y-3">
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Nama Lengkap & Gelar Wali Kelas *</label>
                            <input type="text" name="homeroom_teacher_name" value="{{ $teachingClass->homeroom_teacher_name }}" required
                                   class="w-full rounded-xl border border-slate-300 px-3 py-2 font-semibold">
                        </div>
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">NIP Wali Kelas</label>
                            <input type="text" name="homeroom_teacher_nip" value="{{ $teachingClass->homeroom_teacher_nip }}"
                                   class="w-full rounded-xl border border-slate-300 px-3 py-2">
                        </div>

                        @if(!empty($teachingClass->homeroom_teacher_signature))
                            <div class="p-2.5 rounded-xl bg-slate-50 border border-slate-200 flex items-center justify-between">
                                <img src="{{ $teachingClass->homeroom_teacher_signature }}" class="max-h-12 bg-white border rounded p-1" alt="TTD Wali Kelas">
                                <label class="flex items-center gap-1.5 text-rose-600 font-semibold">
                                    <input type="checkbox" name="clear_homeroom_signature" value="1"> Hapus TTD
                                </label>
                            </div>
                        @endif

                        <div>
                            <div class="flex items-center justify-between mb-1">
                                <span class="font-semibold text-slate-700">TTD Digital Wali Kelas (Opsional):</span>
                                <button type="button" @click="wkPad && wkPad.clear()" class="text-rose-600 font-semibold hover:underline">Bersihkan</button>
                            </div>
                            <canvas x-ref="wkCanvas" width="300" height="100"
                                    class="w-full h-24 rounded-xl border-2 border-dashed border-slate-300 bg-slate-50 cursor-crosshair touch-none"></canvas>
                            <input type="hidden" name="homeroom_teacher_signature_data" :value="wkSigData">
                            <input type="file" name="homeroom_teacher_signature_file" accept="image/*" class="mt-2 w-full text-xs">
                        </div>
                    </div>
                @else
                    {{-- Tampilan Rombel & Wali Kelas Terpusat untuk Guru --}}
                    <input type="hidden" name="grade_level" value="{{ $teachingClass->grade_level }}">
                    <input type="hidden" name="class_name" value="{{ $teachingClass->class_name }}">
                    <input type="hidden" name="homeroom_teacher_name" value="{{ $teachingClass->homeroom_teacher_name }}">
                    <input type="hidden" name="homeroom_teacher_nip" value="{{ $teachingClass->homeroom_teacher_nip }}">

                    <div class="pt-3 border-t border-slate-100 space-y-3">
                        <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200 space-y-2.5">
                            <div class="flex items-center justify-between">
                                <span class="font-bold text-slate-700">Rombongan Belajar</span>
                                <span class="px-2 py-0.5 rounded-md bg-emerald-100 text-emerald-800 font-bold text-[10px]">Master Admin</span>
                            </div>
                            <div class="text-slate-800">
                                <span class="font-extrabold text-sm text-slate-900">{{ $teachingClass->class_name }}</span>
                                <span class="text-slate-500 font-medium">(Tingkat {{ $teachingClass->grade_level }})</span>
                            </div>

                            <div class="pt-2 border-t border-slate-200/60 text-slate-700 space-y-1">
                                <div class="text-[11px] text-slate-500 font-medium">Wali Kelas:</div>
                                <div class="font-bold text-slate-900">{{ $teachingClass->homeroom_teacher_name ?: '-' }}</div>
                                @if($teachingClass->homeroom_teacher_nip)
                                    <div class="text-[11px] text-slate-500">NIP. {{ $teachingClass->homeroom_teacher_nip }}</div>
                                @endif
                                @if(!empty($teachingClass->homeroom_teacher_signature))
                                    <div class="pt-1.5">
                                        <img src="{{ $teachingClass->homeroom_teacher_signature }}" class="max-h-12 bg-white border border-slate-200 rounded p-1" alt="TTD Wali Kelas">
                                    </div>
                                @endif
                            </div>
                            <div class="text-[10px] text-slate-400 italic">
                                Info rombel & wali kelas di atas sinkron otomatis dari Master Sekolah. Hubungi Administrator untuk perbaikan nama atau TTD wali kelas.
                            </div>
                        </div>
                    </div>
                @endif
            </div>

            <button type="submit" class="w-full py-2.5 rounded-xl bg-emerald-600 text-white font-bold hover:bg-emerald-700 transition">
                Simpan Pengaturan Kelas
            </button>
        </form>

        <form method="POST" action="{{ route('classes.destroy', $teachingClass) }}"
              onsubmit="return confirm('Hapus buku administrasi {{ $teachingClass->class_name }} ({{ $teachingClass->subject->name }}) beserta Jurnal, Absensi, dan Nilainya? (Data master rombel murid tidak akan terhapus).')"
              class="bg-rose-50/60 rounded-2xl border border-rose-200 p-4 text-xs flex items-center justify-between">
            <div>
                <div class="font-bold text-rose-900">Hapus Buku Kelas Ini</div>
                <div class="text-[11px] text-rose-700">Tindakan permanen untuk buku kelas ini.</div>
            </div>
            @csrf
            @method('DELETE')
            <button type="submit" class="px-3 py-1.5 rounded-xl bg-rose-600 text-white font-bold hover:bg-rose-700">Hapus Kelas</button>
        </form>
    </div>

    @if(auth()->user()->isAdmin())
        {{-- Modal Tambah Murid (Hanya Admin) --}}
        <div x-show="addStudentModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
            <div @click.away="addStudentModal = false" class="bg-white rounded-2xl border border-slate-200 shadow-xl max-w-md w-full overflow-hidden">
                <div class="px-5 py-4 bg-slate-900 text-white flex items-center justify-between">
                    <h3 class="text-sm font-bold">Tambah Peserta Didik (Admin)</h3>
                    <button type="button" @click="addStudentModal = false" class="text-slate-400 hover:text-white text-xl">&times;</button>
                </div>
                <form method="POST" action="{{ route('classes.students.store', $teachingClass) }}" class="p-5 space-y-3 text-xs">
                    @csrf
                    <div class="grid grid-cols-3 gap-3">
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">No. Urut</label>
                            <input type="number" name="order_number" value="{{ $students->count() + 1 }}" min="1"
                                   class="w-full rounded-xl border border-slate-300 px-3 py-2 font-bold">
                        </div>
                        <div class="col-span-2">
                            <label class="block font-bold text-slate-700 mb-1">NIS / NISN</label>
                            <input type="text" name="nis_nisn" placeholder="Opsional" class="w-full rounded-xl border border-slate-300 px-3 py-2">
                        </div>
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Nama Lengkap *</label>
                        <input type="text" name="full_name" required class="w-full rounded-xl border border-slate-300 px-3 py-2">
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Nama Panggilan</label>
                            <input type="text" name="nickname" placeholder="Untuk kolom Absensi" class="w-full rounded-xl border border-slate-300 px-3 py-2">
                        </div>
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Jenis Kelamin *</label>
                            <select name="gender" class="w-full rounded-xl border border-slate-300 px-3 py-2 font-bold">
                                <option value="L">Laki-laki (L)</option>
                                <option value="P">Perempuan (P)</option>
                            </select>
                        </div>
                    </div>
                    <div class="pt-3 flex justify-end gap-2 border-t border-slate-100">
                        <button type="button" @click="addStudentModal = false" class="px-4 py-2 rounded-xl border border-slate-300 font-semibold">Batal</button>
                        <button type="submit" class="px-4 py-2 rounded-xl bg-emerald-600 text-white font-bold hover:bg-emerald-700">Simpan Murid</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Modal Edit Murid (Hanya Admin) --}}
        <div x-show="editStudent !== null" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
            <template x-if="editStudent">
                <div @click.away="editStudent = null" class="bg-white rounded-2xl border border-slate-200 shadow-xl max-w-md w-full overflow-hidden">
                    <div class="px-5 py-4 bg-slate-900 text-white flex items-center justify-between">
                        <h3 class="text-sm font-bold">Edit Peserta Didik (Admin)</h3>
                        <button type="button" @click="editStudent = null" class="text-slate-400 hover:text-white text-xl">&times;</button>
                    </div>
                    <form method="POST" :action="editStudent.update_url" class="p-5 space-y-3 text-xs">
                        @csrf
                        @method('PUT')
                        <div class="grid grid-cols-3 gap-3">
                            <div>
                                <label class="block font-bold text-slate-700 mb-1">No. Urut *</label>
                                <input type="number" name="order_number" x-model="editStudent.order_number" min="1" required
                                       class="w-full rounded-xl border border-slate-300 px-3 py-2 font-bold">
                            </div>
                            <div class="col-span-2">
                                <label class="block font-bold text-slate-700 mb-1">NIS / NISN</label>
                                <input type="text" name="nis_nisn" x-model="editStudent.nis_nisn" class="w-full rounded-xl border border-slate-300 px-3 py-2">
                            </div>
                        </div>
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Nama Lengkap *</label>
                            <input type="text" name="full_name" x-model="editStudent.full_name" required class="w-full rounded-xl border border-slate-300 px-3 py-2">
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block font-bold text-slate-700 mb-1">Nama Panggilan</label>
                                <input type="text" name="nickname" x-model="editStudent.nickname" class="w-full rounded-xl border border-slate-300 px-3 py-2">
                            </div>
                            <div>
                                <label class="block font-bold text-slate-700 mb-1">Jenis Kelamin *</label>
                                <select name="gender" x-model="editStudent.gender" class="w-full rounded-xl border border-slate-300 px-3 py-2 font-bold">
                                    <option value="L">Laki-laki (L)</option>
                                    <option value="P">Perempuan (P)</option>
                                </select>
                            </div>
                        </div>
                        <div class="pt-3 flex justify-end gap-2 border-t border-slate-100">
                            <button type="button" @click="editStudent = null" class="px-4 py-2 rounded-xl border border-slate-300 font-semibold">Batal</button>
                            <button type="submit" class="px-4 py-2 rounded-xl bg-emerald-600 text-white font-bold hover:bg-emerald-700">Simpan</button>
                        </div>
                    </form>
                </div>
            </template>
        </div>

        {{-- Modal Import Excel Murid (Hanya Admin) --}}
        <div x-show="importModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
            <div @click.away="importModal = false" class="bg-white rounded-2xl border border-slate-200 shadow-xl max-w-md w-full overflow-hidden">
                <div class="px-5 py-4 bg-slate-900 text-white flex items-center justify-between">
                    <h3 class="text-sm font-bold">Import Daftar Murid dari Excel (Admin)</h3>
                    <button type="button" @click="importModal = false" class="text-slate-400 hover:text-white text-xl">&times;</button>
                </div>
                <form method="POST" action="{{ route('classes.students.import', $teachingClass) }}" enctype="multipart/form-data" class="p-5 space-y-4 text-xs">
                    @csrf
                    <div class="p-3 rounded-xl bg-indigo-50 border border-indigo-200 text-indigo-900 flex items-center justify-between">
                        <span>Unduh format template Excel resmi terlebih dahulu:</span>
                        <a href="{{ route('classes.students.template', $teachingClass) }}"
                           class="px-3 py-1.5 rounded-lg bg-indigo-600 text-white font-bold hover:bg-indigo-700 shrink-0">
                            Unduh Template
                        </a>
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Pilih File Excel (.xlsx / .xls / .csv) *</label>
                        <input type="file" name="excel_file" accept=".xlsx,.xls,.csv" required class="w-full text-xs">
                    </div>
                    <label class="flex items-center gap-2 cursor-pointer text-rose-700 font-medium">
                        <input type="checkbox" name="replace_existing" value="1" class="rounded border-slate-300 text-rose-600">
                        <span>Ganti (timpa) seluruh daftar murid lama di kelas ini</span>
                    </label>
                    <div class="pt-3 flex justify-end gap-2 border-t border-slate-100">
                        <button type="button" @click="importModal = false" class="px-4 py-2 rounded-xl border border-slate-300 font-semibold">Batal</button>
                        <button type="submit" class="px-4 py-2 rounded-xl bg-emerald-600 text-white font-bold hover:bg-emerald-700">Mulai Import</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Modal Salin Murid dari Kelas Lain (Hanya Admin) --}}
        <div x-show="copyModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
            <div @click.away="copyModal = false" class="bg-white rounded-2xl border border-slate-200 shadow-xl max-w-md w-full overflow-hidden">
                <div class="px-5 py-4 bg-slate-900 text-white flex items-center justify-between">
                    <h3 class="text-sm font-bold">Salin Murid dari Kelas Lain (Admin)</h3>
                    <button type="button" @click="copyModal = false" class="text-slate-400 hover:text-white text-xl">&times;</button>
                </div>
                <form method="POST" action="{{ route('classes.students.copy', $teachingClass) }}" class="p-5 space-y-3.5 text-xs">
                    @csrf
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Pilih Kelas Sumber *</label>
                        <select name="source_class_id" required class="w-full rounded-xl border border-slate-300 px-3 py-2">
                            @foreach($otherClasses as $oc)
                                <option value="{{ $oc->id }}">
                                    {{ $oc->class_name }} — {{ $oc->subject->name ?? '' }} ({{ $oc->academicYear->year_name ?? '' }} {{ $oc->academicYear->semester ?? '' }} • {{ $oc->students_count }} murid)
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="copy_homeroom" value="1" class="rounded border-slate-300 text-emerald-600">
                        <span class="font-medium text-slate-700">Sekaligus salin Nama & NIP Wali Kelas dari kelas tersebut</span>
                    </label>
                    <div class="pt-3 flex justify-end gap-2 border-t border-slate-100">
                        <button type="button" @click="copyModal = false" class="px-4 py-2 rounded-xl border border-slate-300 font-semibold">Batal</button>
                        <button type="submit" class="px-4 py-2 rounded-xl bg-emerald-600 text-white font-bold hover:bg-emerald-700">Salin Murid</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
@endsection
