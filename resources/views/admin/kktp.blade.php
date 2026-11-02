@extends('layouts.app')

@section('title', 'Pengaturan Mapel & KKM/KKTP')
@section('page_title', 'Master Mata Pelajaran & Standar KKM / KKTP')
@section('page_subtitle', 'Hanya Admin yang dapat mengubah standar KKM/KKTP. Perubahan langsung berlaku otomatis pada seluruh buku nilai guru.')

@section('content')
<div class="space-y-5" x-data="{ addSubjectModal: false }">
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
                                                class="px-2.5 py-1 rounded-lg bg-rose-50 text-rose-700 font-semibold hover:bg-rose-100">
                                            Hapus
                                        </button>
                                    @else
                                        <span class="text-[11px] text-slate-400">Aktif</span>
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
</div>
@endsection
