@extends('layouts.app')

@section('title', 'Rombel ' . $schoolClass->name . ' — Murid & Wali Kelas')
@section('page_title', 'Kelola Rombel: ' . $schoolClass->name . ' (Tingkat ' . $schoolClass->grade_level . ')')
@section('page_subtitle', 'Tahun Ajaran: ' . $schoolClass->academicYear->full_label . ' • ' . $students->count() . ' Peserta Didik Terdaftar')

@section('content')
<div class="mb-3">
    <a href="{{ route('admin.classes.index') }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-500 hover:text-slate-900 transition">
        &larr; Kembali ke Daftar Master Rombel
    </a>
</div>

<div class="grid grid-cols-1 xl:grid-cols-12 gap-5"
     x-data="{
        addStudentModal: false,
        importModal: false,
        editStudent: null,
        wkSigData: '',
        wkPad: null,
        init() {
            this.$nextTick(() => {
                if (this.$refs.wkCanvas) {
                    this.wkPad = window.initSignatureCanvas(this.$refs.wkCanvas, (d) => this.wkSigData = d);
                }
            });
        }
     }">

    {{-- Kolom Kiri: Tabel Peserta Didik & Aksi --}}
    <div class="xl:col-span-8 space-y-4">
        <div class="bg-white rounded-2xl border border-slate-200 p-4 shadow-2xs flex flex-wrap items-center justify-between gap-2">
            <div>
                <h2 class="text-sm font-bold text-slate-900">Daftar Peserta Didik ({{ $students->count() }} Murid)</h2>
                <p class="text-xs text-slate-500">Semua guru mata pelajaran di Rombel ini akan otomatis menggunakan data murid ini.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2 text-xs">
                <button type="button" @click="addStudentModal = true"
                        class="px-3.5 py-2 rounded-xl bg-emerald-600 text-white font-bold hover:bg-emerald-700 shadow-2xs transition">
                    + Tambah Murid
                </button>
                <button type="button" @click="importModal = true"
                        class="px-3.5 py-2 rounded-xl bg-indigo-50 text-indigo-700 border border-indigo-200 font-bold hover:bg-indigo-100 transition">
                    Import Excel Murid
                </button>
                <a href="{{ route('admin.classes.students.template', $schoolClass) }}" data-skip-dirty-guard="1"
                   class="px-3 py-2 rounded-xl border border-slate-200 bg-white text-slate-700 font-semibold hover:bg-slate-50 transition">
                    Unduh Template
                </a>
                @if($students->count() > 1)
                    <form method="POST" action="{{ route('admin.classes.students.sort', $schoolClass) }}">
                        @csrf
                        <button type="submit" class="px-3 py-2 rounded-xl border border-slate-300 text-slate-700 font-semibold hover:bg-slate-50 transition">
                            Urut Abjad A-Z
                        </button>
                    </form>
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
                            <th class="px-3 py-3 w-20 text-center">L / P</th>
                            <th class="px-4 py-3 w-36 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($students as $st)
                            <tr class="hover:bg-slate-50/70">
                                <td class="px-3 py-2.5 text-center font-bold text-slate-700">{{ $st->order_number }}</td>
                                <td class="px-3 py-2.5 font-mono text-slate-600">{{ $st->nis_nisn ?: '-' }}</td>
                                <td class="px-4 py-2.5 font-bold text-slate-900">{{ $st->full_name }}</td>
                                <td class="px-3 py-2.5 text-center">
                                    <span class="px-2 py-0.5 rounded-full font-bold text-[11px] {{ $st->gender === 'P' ? 'bg-pink-50 text-pink-700' : 'bg-sky-50 text-sky-700' }}">
                                        {{ $st->gender }}
                                    </span>
                                </td>
                                <td class="px-4 py-2.5 text-right">
                                    <div class="inline-flex items-center gap-1.5">
                                        <button type="button"
                                                @click="editStudent = @js([
                                                    'order_number' => $st->order_number,
                                                    'nis_nisn' => $st->nis_nisn,
                                                    'full_name' => $st->full_name,
                                                    'gender' => $st->gender,
                                                    'update_url' => route('admin.classes.students.update', [$schoolClass, $st]),
                                                ])"
                                                class="px-2.5 py-1 rounded-lg bg-slate-100 text-slate-700 font-semibold hover:bg-slate-200">
                                            Edit
                                        </button>
                                        <form method="POST" action="{{ route('admin.classes.students.destroy', [$schoolClass, $st]) }}"
                                              onsubmit="return confirm('Hapus murid {{ $st->full_name }} dari Rombel ini?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="px-2.5 py-1 rounded-lg bg-rose-50 text-rose-700 font-semibold hover:bg-rose-100">
                                                Hapus
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-5 py-10 text-center text-slate-500">
                                    Belum ada peserta didik di Rombel ini. Klik <strong>+ Tambah Murid</strong> atau <strong>Import Excel Murid</strong>.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Kolom Kanan: Pengaturan Identitas Rombel & Wali Kelas (+ TTD) --}}
    <div class="xl:col-span-4 space-y-4">
        <form method="POST" action="{{ route('admin.classes.update', $schoolClass) }}" enctype="multipart/form-data"
              class="bg-white rounded-2xl border border-slate-200 shadow-2xs p-5 space-y-4 text-xs">
            @csrf
            @method('PUT')

            <div>
                <h3 class="text-sm font-bold text-slate-900">Identitas Rombel & Wali Kelas</h3>
                <p class="text-[11px] text-slate-500">Perubahan di sini otomatis berlaku pada kop/tanda tangan seluruh guru pengampu.</p>
            </div>

            <div class="space-y-3">
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Tingkat *</label>
                        <select name="grade_level" required class="w-full rounded-xl border border-slate-300 px-3 py-2 font-bold">
                            @foreach($gradeLevels as $lvl)
                                <option value="{{ $lvl }}" @selected($lvl === $schoolClass->grade_level)>Kelas {{ $lvl }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Nama Rombel *</label>
                        <input type="text" name="name" value="{{ old('name', $schoolClass->name) }}" required
                               class="w-full rounded-xl border border-slate-300 px-3 py-2 font-bold">
                    </div>
                </div>

                <div>
                    <label class="block font-bold text-slate-700 mb-1">Nama Lengkap & Gelar Wali Kelas *</label>
                    <input type="text" name="homeroom_teacher_name" value="{{ old('homeroom_teacher_name', $schoolClass->homeroom_teacher_name) }}" required
                           class="w-full rounded-xl border border-slate-300 px-3 py-2 font-semibold">
                </div>

                <div>
                    <label class="block font-bold text-slate-700 mb-1">NIP Wali Kelas</label>
                    <input type="text" name="homeroom_teacher_nip" value="{{ old('homeroom_teacher_nip', $schoolClass->homeroom_teacher_nip) }}"
                           class="w-full rounded-xl border border-slate-300 px-3 py-2">
                </div>

                {{-- Tanda Tangan Digital Wali Kelas --}}
                <div class="pt-3 border-t border-slate-100 space-y-2.5">
                    <label class="block font-bold text-slate-700">Tanda Tangan Digital Wali Kelas</label>
                    @if(!empty($schoolClass->homeroom_teacher_signature))
                        <div class="p-3 rounded-xl bg-slate-50 border border-slate-200 flex items-center justify-between gap-3">
                            <img src="{{ $schoolClass->homeroom_teacher_signature }}" class="h-12 max-w-[140px] object-contain bg-white border rounded p-1" alt="TTD Wali Kelas">
                            <label class="flex items-center gap-1.5 text-rose-600 font-semibold cursor-pointer text-xs">
                                <input type="checkbox" name="clear_homeroom_signature" value="1"> Hapus TTD
                            </label>
                        </div>
                    @endif

                    <div class="space-y-1.5">
                        <span class="text-[11px] text-slate-500 font-medium">Unggah Berkas Gambar TTD:</span>
                        <input type="file" name="homeroom_teacher_signature_file" accept="image/*" class="w-full text-xs">
                    </div>

                    <div class="space-y-1.5">
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] text-slate-500 font-medium">Atau Buat TTD di Kanvas:</span>
                            <button type="button" @click="if(wkPad) { wkPad.clear(); wkSigData = ''; }" class="text-[11px] text-rose-600 underline">Bersihkan</button>
                        </div>
                        <div class="border border-slate-300 rounded-xl overflow-hidden bg-slate-50">
                            <canvas x-ref="wkCanvas" width="320" height="110" class="w-full h-24 touch-none cursor-crosshair bg-white"></canvas>
                        </div>
                        <input type="hidden" name="homeroom_teacher_signature_data" :value="wkSigData">
                    </div>
                </div>
            </div>

            <div class="pt-2">
                <button type="submit" class="w-full py-2.5 rounded-xl bg-emerald-600 text-white font-bold hover:bg-emerald-700 transition">
                    Simpan Perubahan Rombel
                </button>
            </div>
        </form>

        {{-- Kartu Guru Pengampu Terhubung --}}
        <div class="bg-white rounded-2xl border border-slate-200 shadow-2xs p-4 space-y-2 text-xs">
            <h4 class="font-bold text-slate-900">Guru Pengampu Terhubung di Rombel Ini:</h4>
            @if($schoolClass->teachingClasses->isEmpty())
                <p class="text-slate-400">Belum ada kelas ajar guru yang terhubung ke rombel ini.</p>
            @else
                <div class="divide-y divide-slate-100">
                    @foreach($schoolClass->teachingClasses as $tc)
                        <div class="py-2 flex items-center justify-between gap-2">
                            <div>
                                <div class="font-bold text-slate-800">{{ $tc->subject->name ?? '-' }}</div>
                                <div class="text-[11px] text-slate-500">{{ $tc->teacher->name ?? '-' }} (NIP. {{ $tc->teacher->nip ?: '-' }})</div>
                            </div>
                            <a href="{{ route('grades.index', $tc) }}" class="px-2.5 py-1 rounded-lg bg-slate-100 text-slate-700 font-semibold hover:bg-slate-200 text-[11px]">
                                Buka Nilai &rarr;
                            </a>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    {{-- Modal Tambah Murid --}}
    <div x-show="addStudentModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
        <div @click.away="addStudentModal = false" class="bg-white rounded-2xl border border-slate-200 shadow-xl max-w-md w-full overflow-hidden">
            <div class="px-5 py-4 bg-slate-900 text-white flex items-center justify-between">
                <h3 class="text-sm font-bold">Tambah Murid ke Rombel {{ $schoolClass->name }}</h3>
                <button type="button" @click="addStudentModal = false" class="text-slate-400 hover:text-white text-xl leading-none">&times;</button>
            </div>
            <form method="POST" action="{{ route('admin.classes.students.store', $schoolClass) }}" class="p-5 space-y-3.5 text-xs">
                @csrf
                <div class="grid grid-cols-3 gap-2.5">
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">No. Urut</label>
                        <input type="number" name="order_number" value="{{ $students->count() + 1 }}" min="1"
                               class="w-full rounded-xl border border-slate-300 px-3 py-2 text-center font-bold">
                    </div>
                    <div class="col-span-2">
                        <label class="block font-bold text-slate-700 mb-1">NIS / NISN</label>
                        <input type="text" name="nis_nisn" placeholder="0098123456"
                               class="w-full rounded-xl border border-slate-300 px-3 py-2 font-mono">
                    </div>
                </div>
                <div>
                    <label class="block font-bold text-slate-700 mb-1">Nama Lengkap Murid *</label>
                    <input type="text" name="full_name" required placeholder="Contoh: Muhammad Rizky Pratama"
                           class="w-full rounded-xl border border-slate-300 px-3 py-2 font-bold">
                </div>
                <div>
                    <label class="block font-bold text-slate-700 mb-1">Jenis Kelamin *</label>
                    <select name="gender" required class="w-full rounded-xl border border-slate-300 px-3 py-2 font-bold">
                        <option value="L">Laki-laki (L)</option>
                        <option value="P">Perempuan (P)</option>
                    </select>
                </div>
                <div class="pt-3 flex justify-end gap-2 border-t border-slate-100">
                    <button type="button" @click="addStudentModal = false" class="px-4 py-2 rounded-xl border border-slate-300 font-semibold">Batal</button>
                    <button type="submit" class="px-5 py-2 rounded-xl bg-emerald-600 text-white font-bold hover:bg-emerald-700">Simpan Murid</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Modal Edit Murid --}}
    <div x-show="editStudent !== null" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
        <template x-if="editStudent">
            <div @click.away="editStudent = null" class="bg-white rounded-2xl border border-slate-200 shadow-xl max-w-md w-full overflow-hidden">
                <div class="px-5 py-4 bg-slate-900 text-white flex items-center justify-between">
                    <h3 class="text-sm font-bold">Edit Data Murid</h3>
                    <button type="button" @click="editStudent = null" class="text-slate-400 hover:text-white text-xl leading-none">&times;</button>
                </div>
                <form method="POST" :action="editStudent.update_url" class="p-5 space-y-3.5 text-xs">
                    @csrf
                    @method('PUT')
                    <div class="grid grid-cols-3 gap-2.5">
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">No. Urut *</label>
                            <input type="number" name="order_number" x-model="editStudent.order_number" required min="1"
                                   class="w-full rounded-xl border border-slate-300 px-3 py-2 text-center font-bold">
                        </div>
                        <div class="col-span-2">
                            <label class="block font-bold text-slate-700 mb-1">NIS / NISN</label>
                            <input type="text" name="nis_nisn" x-model="editStudent.nis_nisn"
                                   class="w-full rounded-xl border border-slate-300 px-3 py-2 font-mono">
                        </div>
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Nama Lengkap Murid *</label>
                        <input type="text" name="full_name" x-model="editStudent.full_name" required
                               class="w-full rounded-xl border border-slate-300 px-3 py-2 font-bold">
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Jenis Kelamin *</label>
                        <select name="gender" x-model="editStudent.gender" required class="w-full rounded-xl border border-slate-300 px-3 py-2 font-bold">
                            <option value="L">Laki-laki (L)</option>
                            <option value="P">Perempuan (P)</option>
                        </select>
                    </div>
                    <div class="pt-3 flex justify-end gap-2 border-t border-slate-100">
                        <button type="button" @click="editStudent = null" class="px-4 py-2 rounded-xl border border-slate-300 font-semibold">Batal</button>
                        <button type="submit" class="px-5 py-2 rounded-xl bg-emerald-600 text-white font-bold hover:bg-emerald-700">Simpan Perubahan</button>
                    </div>
                </form>
            </div>
        </template>
    </div>

    {{-- Modal Import Excel Murid --}}
    <div x-show="importModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
        <div @click.away="importModal = false" class="bg-white rounded-2xl border border-slate-200 shadow-xl max-w-md w-full overflow-hidden">
            <div class="px-5 py-4 bg-slate-900 text-white flex items-center justify-between">
                <h3 class="text-sm font-bold">Import Peserta Didik dari Excel</h3>
                <button type="button" @click="importModal = false" class="text-slate-400 hover:text-white text-xl leading-none">&times;</button>
            </div>
            <form method="POST" action="{{ route('admin.classes.students.import', $schoolClass) }}" enctype="multipart/form-data" class="p-5 space-y-4 text-xs">
                @csrf
                <div class="p-3 rounded-xl bg-indigo-50 border border-indigo-200 text-indigo-900 flex items-center justify-between gap-2">
                    <span>Gunakan template resmi untuk format NISN, nama, dan L/P:</span>
                    <a href="{{ route('admin.classes.students.template', $schoolClass) }}" data-skip-dirty-guard="1"
                       class="px-3 py-1.5 rounded-lg bg-indigo-600 text-white font-bold hover:bg-indigo-700 shrink-0">
                        Unduh Template
                    </a>
                </div>
                <div>
                    <label class="block font-bold text-slate-700 mb-1">Pilih File Excel (.xlsx / .xls / .csv) *</label>
                    <input type="file" name="excel_file" accept=".xlsx,.xls,.csv" required class="w-full text-xs">
                </div>
                <div class="space-y-1 pt-1">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="replace_existing" value="1" class="rounded border-slate-300 text-emerald-600">
                        <span class="text-slate-700 font-semibold">Gantikan (hapus) seluruh daftar murid lama rombel ini</span>
                    </label>
                    <p class="text-[11px] text-slate-500">Biarkan tidak dicentang jika ingin menambahkan murid baru ke daftar yang sudah ada.</p>
                </div>
                <div class="pt-3 flex justify-end gap-2 border-t border-slate-100">
                    <button type="button" @click="importModal = false" class="px-4 py-2 rounded-xl border border-slate-300 font-semibold">Batal</button>
                    <button type="submit" class="px-4 py-2 rounded-xl bg-emerald-600 text-white font-bold hover:bg-emerald-700">Mulai Import Murid</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
