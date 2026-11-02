@extends('layouts.app')

@section('title', 'Manajemen Akun Guru')
@section('page_title', 'Manajemen Akun Guru & Administrator')
@section('page_subtitle', 'Buat akun guru baru, atur NIP, reset kata sandi, dan kontrol status akses pengguna.')

@section('content')
<div class="space-y-5" x-data="{ createModal: false, editUser: null }">
    <div class="bg-white rounded-2xl border border-slate-200 p-4 shadow-2xs flex flex-wrap items-center justify-between gap-3">
        <form method="GET" action="{{ route('admin.teachers.index') }}" class="flex items-center gap-2 flex-1 max-w-md">
            <input type="text" name="q" value="{{ $search }}" placeholder="Cari nama guru, NIP, email, atau mapel..."
                   class="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-xs">
            <button type="submit" class="px-3.5 py-2 rounded-xl bg-slate-900 text-white text-xs font-bold hover:bg-slate-800">Cari</button>
        </form>

        <button type="button" @click="createModal = true"
                class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-emerald-600 text-white text-xs font-bold hover:bg-emerald-700 shadow-2xs transition">
            <span>+ Tambah Akun Guru / Admin</span>
        </button>
    </div>

    <div class="bg-white rounded-2xl border border-slate-200 shadow-2xs overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-xs text-left">
                <thead class="bg-slate-50 text-slate-600 uppercase text-[11px] border-b border-slate-200">
                    <tr>
                        <th class="px-4 py-3">Nama Lengkap & NIP</th>
                        <th class="px-4 py-3">Email & Kontak</th>
                        <th class="px-3 py-3 text-center">Peran</th>
                        <th class="px-3 py-3">Mapel Utama</th>
                        <th class="px-3 py-3 text-center">Jml Kelas ({{ $selectedAcademicYear->semester }})</th>
                        <th class="px-3 py-3 text-center">TTD Digital</th>
                        <th class="px-3 py-3 text-center">Status</th>
                        <th class="px-4 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($users as $u)
                        <tr class="hover:bg-slate-50/70">
                            <td class="px-4 py-3">
                                <div class="font-bold text-slate-900">{{ $u->name }}</div>
                                <div class="text-[11px] text-slate-500">NIP. {{ $u->nip ?: '-' }}</div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="text-slate-800 font-medium">{{ $u->email }}</div>
                                <div class="text-[11px] text-slate-500">{{ $u->phone ?: '-' }}</div>
                            </td>
                            <td class="px-3 py-3 text-center">
                                <span class="px-2 py-0.5 rounded-full text-[11px] font-bold {{ $u->isAdmin() ? 'bg-purple-50 text-purple-700 border border-purple-200' : 'bg-emerald-50 text-emerald-700 border border-emerald-200' }}">
                                    {{ strtoupper($u->role) }}
                                </span>
                            </td>
                            <td class="px-3 py-3 text-slate-700">{{ $u->default_subject ?: '-' }}</td>
                            <td class="px-3 py-3 text-center">
                                <a href="{{ route('admin.dashboard', ['teacher_id' => $u->id]) }}"
                                   class="font-bold text-indigo-600 hover:underline">
                                    {{ $u->semester_classes_count }} kelas
                                </a>
                            </td>
                            <td class="px-3 py-3 text-center">
                                @if(!empty($u->signature_data))
                                    <span class="px-2 py-0.5 rounded bg-emerald-50 text-emerald-700 text-[10px] font-bold">Tersedia</span>
                                @else
                                    <span class="text-[11px] text-slate-400">Belum</span>
                                @endif
                            </td>
                            <td class="px-3 py-3 text-center">
                                <span class="px-2 py-0.5 rounded-full text-[11px] font-bold {{ $u->is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' }}">
                                    {{ $u->is_active ? 'Aktif' : 'Nonaktif' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="inline-flex items-center gap-1.5">
                                    <button type="button"
                                            @click="editUser = @js([
                                                'id' => $u->id,
                                                'name' => $u->name,
                                                'email' => $u->email,
                                                'nip' => $u->nip,
                                                'role' => $u->role,
                                                'phone' => $u->phone,
                                                'default_subject' => $u->default_subject,
                                                'is_active' => $u->is_active,
                                                'update_url' => route('admin.teachers.update', $u),
                                            ])"
                                            class="px-2.5 py-1 rounded-lg bg-slate-100 text-slate-700 font-semibold hover:bg-slate-200">
                                        Edit / Sandi
                                    </button>

                                    @if($u->id !== auth()->id())
                                        <form method="POST" action="{{ route('admin.teachers.toggle', $u) }}">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit"
                                                    class="px-2.5 py-1 rounded-lg font-semibold {{ $u->is_active ? 'bg-amber-50 text-amber-800 hover:bg-amber-100' : 'bg-emerald-50 text-emerald-700 hover:bg-emerald-100' }}">
                                                {{ $u->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                                            </button>
                                        </form>
                                        <form method="POST" action="{{ route('admin.teachers.destroy', $u) }}"
                                              onsubmit="return confirm('Hapus akun {{ $u->name }} beserta seluruh data kelasnya?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="px-2.5 py-1 rounded-lg bg-rose-50 text-rose-700 font-semibold hover:bg-rose-100">
                                                Hapus
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- Modal Tambah Akun Guru --}}
    <div x-show="createModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
        <div @click.away="createModal = false" class="bg-white rounded-2xl border border-slate-200 shadow-xl max-w-lg w-full overflow-hidden">
            <div class="px-5 py-4 bg-slate-900 text-white flex items-center justify-between">
                <h3 class="text-sm font-bold">Buat Akun Guru / Pengguna Baru</h3>
                <button type="button" @click="createModal = false" class="text-slate-400 hover:text-white text-xl">&times;</button>
            </div>
            <form method="POST" action="{{ route('admin.teachers.store') }}" class="p-5 space-y-3.5 text-xs">
                @csrf
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div class="sm:col-span-2">
                        <label class="block font-bold text-slate-700 mb-1">Nama Lengkap & Gelar *</label>
                        <input type="text" name="name" required placeholder="Contoh: Rina Marlina, S.Pd., Gr."
                               class="w-full rounded-xl border border-slate-300 px-3 py-2">
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">NIP / NUPTK</label>
                        <input type="text" name="nip" placeholder="19871203 201402 2 002"
                               class="w-full rounded-xl border border-slate-300 px-3 py-2">
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Peran Akses *</label>
                        <select name="role" class="w-full rounded-xl border border-slate-300 px-3 py-2">
                            <option value="guru">Guru Mata Pelajaran</option>
                            <option value="admin">Administrator</option>
                        </select>
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Email Login *</label>
                        <input type="email" name="email" required placeholder="rina@sekolah.sch.id"
                               class="w-full rounded-xl border border-slate-300 px-3 py-2">
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">No. Telepon / WA</label>
                        <input type="text" name="phone" placeholder="0812xxxxxxxx"
                               class="w-full rounded-xl border border-slate-300 px-3 py-2">
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Mata Pelajaran Utama</label>
                        <input type="text" name="default_subject" placeholder="Contoh: IPA"
                               class="w-full rounded-xl border border-slate-300 px-3 py-2">
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 mb-1">Kata Sandi Awal *</label>
                        <input type="text" name="password" value="password123" required
                               class="w-full rounded-xl border border-slate-300 px-3 py-2 font-mono">
                    </div>
                </div>
                <div class="pt-3 flex justify-end gap-2 border-t border-slate-100">
                    <button type="button" @click="createModal = false" class="px-4 py-2 rounded-xl border border-slate-300 font-semibold">Batal</button>
                    <button type="submit" class="px-4 py-2 rounded-xl bg-emerald-600 text-white font-bold hover:bg-emerald-700">Simpan Akun</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Modal Edit Akun Guru --}}
    <div x-show="editUser !== null" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
        <template x-if="editUser">
            <div @click.away="editUser = null" class="bg-white rounded-2xl border border-slate-200 shadow-xl max-w-lg w-full overflow-hidden">
                <div class="px-5 py-4 bg-slate-900 text-white flex items-center justify-between">
                    <h3 class="text-sm font-bold">Edit Akun: <span x-text="editUser.name"></span></h3>
                    <button type="button" @click="editUser = null" class="text-slate-400 hover:text-white text-xl">&times;</button>
                </div>
                <form method="POST" :action="editUser.update_url" class="p-5 space-y-3.5 text-xs">
                    @csrf
                    @method('PUT')
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div class="sm:col-span-2">
                            <label class="block font-bold text-slate-700 mb-1">Nama Lengkap & Gelar *</label>
                            <input type="text" name="name" x-model="editUser.name" required class="w-full rounded-xl border border-slate-300 px-3 py-2">
                        </div>
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">NIP / NUPTK</label>
                            <input type="text" name="nip" x-model="editUser.nip" class="w-full rounded-xl border border-slate-300 px-3 py-2">
                        </div>
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Peran Akses *</label>
                            <select name="role" x-model="editUser.role" class="w-full rounded-xl border border-slate-300 px-3 py-2">
                                <option value="guru">Guru Mata Pelajaran</option>
                                <option value="admin">Administrator</option>
                            </select>
                        </div>
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Email Login *</label>
                            <input type="email" name="email" x-model="editUser.email" required class="w-full rounded-xl border border-slate-300 px-3 py-2">
                        </div>
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">No. Telepon / WA</label>
                            <input type="text" name="phone" x-model="editUser.phone" class="w-full rounded-xl border border-slate-300 px-3 py-2">
                        </div>
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Mata Pelajaran Utama</label>
                            <input type="text" name="default_subject" x-model="editUser.default_subject" class="w-full rounded-xl border border-slate-300 px-3 py-2">
                        </div>
                        <div>
                            <label class="block font-bold text-slate-700 mb-1">Reset Kata Sandi (Opsional)</label>
                            <input type="text" name="password" placeholder="Isi untuk mengganti sandi" class="w-full rounded-xl border border-slate-300 px-3 py-2 font-mono">
                        </div>
                    </div>
                    <div class="pt-3 flex justify-end gap-2 border-t border-slate-100">
                        <button type="button" @click="editUser = null" class="px-4 py-2 rounded-xl border border-slate-300 font-semibold">Batal</button>
                        <button type="submit" class="px-4 py-2 rounded-xl bg-emerald-600 text-white font-bold hover:bg-emerald-700">Simpan Perubahan</button>
                    </div>
                </form>
            </div>
        </template>
    </div>
</div>
@endsection
