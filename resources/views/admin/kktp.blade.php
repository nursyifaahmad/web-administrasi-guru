@extends('layouts.app')

@section('title', 'Pengaturan Mapel & KKM/KKTP')
@section('page_title', 'Master Mata Pelajaran & Standar KKM / KKTP')
@section('page_subtitle', 'Hanya Admin yang dapat mengubah standar KKM/KKTP. Perubahan langsung berlaku otomatis pada seluruh buku nilai guru.')

@section('content')
<div class="space-y-5" x-data="{ addSubjectModal: false, purgeModal: false, purgeTarget: null, confirmInput: '' }">
    <form method="POST" action="{{ route('admin.kktp.matrix.update') }}" class="space-y-4">
        @csrf
        @method('PUT')

        <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-2xs flex flex-col lg:flex-row lg:items-end justify-between gap-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 flex-1 max-w-2xl">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">KKM / KKTP Default Global Sekolah</label>
                    <input type="number" name="global_default_kktp" min="0" max="100" value="{{ $school->global_default_kktp }}" required
                           class="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm font-bold text-indigo-700">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Daftar Tingkat Kelas (pisahkan koma)</label>
                    <input type="text" name="grade_levels_csv" value="{{ implode(', ', $gradeLevels) }}" required
                           placeholder="Contoh: VII, VIII, IX"
                           class="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm font-semibold">
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <button type="button" @click="addSubjectModal = true"
                        class="px-3.5 py-2.5 rounded-xl border border-emerald-300 bg-emerald-50 text-emerald-800 text-xs font-bold hover:bg-emerald-100">
                    + Tambah Mata Pelajaran
                </button>
                <button type="submit"
                        class="px-5 py-2.5 rounded-xl bg-emerald-600 text-white text-xs font-bold hover:bg-emerald-700 shadow-2xs transition">
                    Simpan Seluruh Standar KKM / KKTP
                </button>
            </div>
        </div>

        <div class="bg-white rounded-2xl border border-slate-200 shadow-2xs overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-xs text-left">
                    <thead class="bg-slate-50 text-slate-600 uppercase text-[11px] border-b border-slate-200">
                        <tr>
                            <th class="px-4 py-3 w-28">Kode</th>
                            <th class="px-4 py-3">Nama Mata Pelajaran</th>
                            <th class="px-3 py-3 text-center w-28">KKTP Default</th>
                            @foreach($gradeLevels as $lvl)
                                <th class="px-3 py-3 text-center w-28 bg-indigo-50/60 text-indigo-900">KKTP Kelas {{ $lvl }}</th>
                            @endforeach
                            <th class="px-3 py-3 text-center w-24">Digunakan</th>
                            <th class="px-4 py-3 text-right w-20">Hapus</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($subjects as $sub)
                            <tr class="hover:bg-slate-50/70">
                                <td class="px-4 py-2.5">
                                    <input type="text" name="subjects[{{ $sub->id }}][code]" value="{{ $sub->code }}" required
                                           class="w-full rounded-lg border border-slate-300 px-2.5 py-1.5 text-xs font-bold uppercase text-center">
                                </td>
                                <td class="px-4 py-2.5">
                                    <input type="text" name="subjects[{{ $sub->id }}][name]" value="{{ $sub->name }}" required
                                           class="w-full rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold">
                                </td>
                                <td class="px-3 py-2.5 text-center">
                                    <input type="number" min="0" max="100" name="subjects[{{ $sub->id }}][default_kktp]" value="{{ $sub->default_kktp }}" required
                                           class="w-20 rounded-lg border border-slate-300 px-2 py-1.5 text-xs text-center font-bold">
                                </td>
                                @foreach($gradeLevels as $lvl)
                                    @php $val = $sub->kktpForGrade($lvl, $school->global_default_kktp); @endphp
                                    <td class="px-3 py-2.5 text-center bg-indigo-50/25">
                                        <input type="number" min="0" max="100" name="subjects[{{ $sub->id }}][kktps][{{ $lvl }}]" value="{{ $val }}" required
                                               class="w-20 rounded-lg border border-indigo-200 bg-white px-2 py-1.5 text-xs text-center font-extrabold text-indigo-700 focus:border-indigo-500">
                                    </td>
                                @endforeach
                                <td class="px-3 py-2.5 text-center font-semibold text-slate-600">
                                    {{ $sub->teaching_classes_count }} kelas
                                </td>
                                <td class="px-4 py-2.5 text-right">
                                    @if($sub->teaching_classes_count === 0)
                                        <button type="button"
                                                onclick="if(confirm('Hapus mata pelajaran {{ $sub->name }}?')) document.getElementById('del-sub-{{ $sub->id }}').submit();"
                                                class="px-2.5 py-1 rounded-lg bg-rose-50 text-rose-700 font-semibold hover:bg-rose-100 transition">
                                            Hapus
                                        </button>
                                    @else
                                        <button type="button"
                                                @click="purgeTarget = { id: {{ $sub->id }}, name: '{{ addslashes($sub->name) }}', code: '{{ $sub->code }}', count: {{ $sub->teaching_classes_count }} }; purgeModal = true; confirmInput = ''"
                                                class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg border border-rose-200 bg-rose-50 text-[11px] font-bold text-rose-700 hover:bg-rose-100 transition shadow-2xs"
                                                title="Bersihkan seluruh buku kelas pada mapel ini">
                                            <svg class="w-3 h-3 text-rose-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                            Bersihkan
                                        </button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </form>

    @foreach($subjects as $sub)
        @if($sub->teaching_classes_count === 0)
            <form id="del-sub-{{ $sub->id }}" method="POST" action="{{ route('admin.kktp.subjects.destroy', $sub) }}" class="hidden">
                @csrf
                @method('DELETE')
            </form>
        @endif
    @endforeach

    {{-- Modal Tambah Mata Pelajaran --}}
    <div x-show="addSubjectModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
        <div @click.away="addSubjectModal = false" class="bg-white rounded-2xl border border-slate-200 shadow-xl max-w-md w-full overflow-hidden">
            <div class="px-5 py-4 bg-slate-900 text-white flex items-center justify-between">
                <h3 class="text-sm font-bold">Tambah Mata Pelajaran Baru</h3>
                <button type="button" @click="addSubjectModal = false" class="text-slate-400 hover:text-white text-xl">&times;</button>
            </div>
            <form method="POST" action="{{ route('admin.kktp.subjects.store') }}" class="p-5 space-y-3.5 text-xs">
                @csrf
                <div class="grid grid-cols-3 gap-3">
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Kode *</label>
                        <input type="text" name="code" required placeholder="BK / MULOK" class="w-full rounded-xl border border-slate-300 px-3 py-2 uppercase">
                    </div>
                    <div class="col-span-2">
                        <label class="block font-bold text-slate-700 mb-1">Nama Mata Pelajaran *</label>
                        <input type="text" name="name" required placeholder="Bahasa Sunda" class="w-full rounded-xl border border-slate-300 px-3 py-2">
                    </div>
                </div>
                <div>
                    <label class="block font-bold text-slate-700 mb-1">KKTP Default *</label>
                    <input type="number" name="default_kktp" value="{{ $school->global_default_kktp }}" min="0" max="100" required
                           class="w-full rounded-xl border border-slate-300 px-3 py-2 font-bold">
                </div>
                <div class="grid grid-cols-3 gap-2 pt-1">
                    @foreach($gradeLevels as $lvl)
                        <div>
                            <label class="block font-semibold text-indigo-800 mb-1">Kelas {{ $lvl }}</label>
                            <input type="number" name="grade_kktp[{{ $lvl }}]" value="{{ $school->global_default_kktp }}" min="0" max="100"
                                   class="w-full rounded-xl border border-indigo-200 px-2.5 py-1.5 font-bold text-indigo-700">
                        </div>
                    @endforeach
                </div>
                <div class="pt-3 flex justify-end gap-2 border-t border-slate-100">
                    <button type="button" @click="addSubjectModal = false" class="px-4 py-2 rounded-xl border border-slate-300 font-semibold">Batal</button>
                    <button type="submit" class="px-4 py-2 rounded-xl bg-emerald-600 text-white font-bold hover:bg-emerald-700">Tambahkan</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Modal Konfirmasi Bersihkan Kelas Mapel --}}
    <div x-show="purgeModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
        <div @click.away="purgeModal = false" class="bg-white rounded-2xl border border-slate-200 shadow-2xl max-w-lg w-full overflow-hidden">
            <div class="px-6 py-4 bg-rose-600 text-white flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <svg class="w-5 h-5 text-rose-200" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    <h3 class="text-sm font-bold">Konfirmasi Bersihkan Seluruh Kelas Mapel</h3>
                </div>
                <button type="button" @click="purgeModal = false" class="text-rose-200 hover:text-white text-xl">&times;</button>
            </div>

            <form method="POST" :action="'{{ url('/admin/kktp/subjects') }}/' + purgeTarget?.id + '/purge-classes'" class="p-6 space-y-4">
                @csrf
                <div class="p-4 bg-rose-50 border border-rose-200 rounded-xl space-y-2 text-xs text-rose-900">
                    <p class="font-bold text-sm text-rose-700">
                        Peringatan Pembersihan Buku Kelas
                    </p>
                    <p>
                        Aksi ini akan menghapus sekaligus <strong class="font-extrabold text-rose-800" x-text="purgeTarget?.count"></strong> buku kelas yang menggunakan mata pelajaran <strong class="font-extrabold text-rose-800" x-text="purgeTarget?.name"></strong> (<span class="font-bold" x-text="purgeTarget?.code"></span>) di seluruh akun guru pengampu.
                    </p>
                    <p>
                        Seluruh riwayat absensi, nilai, dan jurnal harian pada kelas-kelas tersebut akan dibersihkan.
                    </p>
                    <div class="pt-2 border-t border-rose-200/80 flex items-start gap-1.5 text-emerald-800 font-semibold">
                        <svg class="w-4 h-4 text-emerald-600 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                        </svg>
                        <span>Keamanan Data Terjamin: Data Master Siswa & Rombel pada menu "Master Rombel & Murid" TETAP 100% UTUH dan TIDAK AKAN TERHAPUS.</span>
                    </div>
                </div>

                <div class="space-y-1.5">
                    <label class="block text-xs font-bold text-slate-700">
                        Ketik kata <span class="text-rose-600 font-extrabold">"HAPUS"</span> untuk mengonfirmasi:
                    </label>
                    <input type="text"
                           name="confirm_text"
                           x-model="confirmInput"
                           placeholder="Ketik HAPUS di sini..."
                           autocomplete="off"
                           required
                           class="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm font-bold text-slate-900 tracking-wider focus:border-rose-500 focus:ring-1 focus:ring-rose-500">
                </div>

                <div class="pt-3 flex justify-end gap-2 border-t border-slate-100">
                    <button type="button" @click="purgeModal = false" class="px-4 py-2 rounded-xl border border-slate-300 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition">
                        Batal
                    </button>
                    <button type="submit"
                            :disabled="confirmInput.trim().toUpperCase() !== 'HAPUS'"
                            class="px-5 py-2 rounded-xl bg-rose-600 text-white text-xs font-bold hover:bg-rose-700 disabled:opacity-40 disabled:cursor-not-allowed transition shadow-2xs">
                        Bersihkan Seluruh Kelas Mapel Ini
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
