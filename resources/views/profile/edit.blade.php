@extends('layouts.app')

@section('title', 'Profil & Tanda Tangan Digital')
@section('page_title', 'Profil Akun & Tanda Tangan Digital')
@section('page_subtitle', 'Data Nama, Gelar, NIP, dan Tanda Tangan Digital otomatis digunakan pada cetakan PDF & Excel resmi.')

@section('content')
<div class="max-w-3xl">
    <form method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data"
          class="bg-white rounded-2xl border border-slate-200 shadow-2xs p-6 space-y-6"
          x-data="{
            sigData: '',
            pad: null,
            init() {
                this.$nextTick(() => {
                    this.pad = window.initSignatureCanvas(this.$refs.sigCanvas, (data) => {
                        this.sigData = data;
                    });
                });
            }
          }">
        @csrf
        @method('PUT')

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Nama Lengkap & Gelar</label>
                <input type="text" name="name" value="{{ old('name', $user->name) }}" required
                       class="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">NIP / NUPTK</label>
                <input type="text" name="nip" value="{{ old('nip', $user->nip) }}" placeholder="Contoh: 19900412 201903 1 008"
                       class="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Email</label>
                <input type="email" name="email" value="{{ old('email', $user->email) }}" required
                       class="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Nomor Telepon / WhatsApp</label>
                <input type="text" name="phone" value="{{ old('phone', $user->phone) }}"
                       class="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm">
            </div>
            <div class="sm:col-span-2">
                <label class="block text-xs font-bold text-slate-700 mb-1">Mata Pelajaran / Jabatan Utama</label>
                <input type="text" name="default_subject" value="{{ old('default_subject', $user->default_subject) }}"
                       class="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm">
            </div>
        </div>

        <div class="pt-4 border-t border-slate-100">
            <h3 class="text-sm font-bold text-slate-900 mb-1">Tanda Tangan / Paraf Digital</h3>
            <p class="text-xs text-slate-500 mb-3">Digunakan untuk Paraf Jurnal Harian dan tanda tangan Guru Mata Pelajaran saat Cetak PDF / Excel.</p>

            @if(!empty($user->signature_data))
                <div class="mb-4 p-3 rounded-xl bg-slate-50 border border-slate-200 flex items-center justify-between gap-4">
                    <div>
                        <div class="text-xs font-semibold text-slate-700 mb-1">Tanda Tangan Saat Ini:</div>
                        <img src="{{ $user->signature_data }}" alt="TTD Tersimpan" class="max-h-16 bg-white border border-slate-200 rounded p-1">
                    </div>
                    <label class="flex items-center gap-2 text-xs text-rose-600 font-semibold cursor-pointer">
                        <input type="checkbox" name="clear_signature" value="1" class="rounded border-slate-300 text-rose-600">
                        <span>Hapus TTD Tersimpan</span>
                    </label>
                </div>
            @endif

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <div class="flex items-center justify-between mb-1">
                        <span class="text-xs font-semibold text-slate-700">Goreskan Tanda Tangan di Kanvas:</span>
                        <button type="button" @click="pad && pad.clear()" class="text-xs text-rose-600 font-semibold hover:underline">Bersihkan Kanvas</button>
                    </div>
                    <canvas x-ref="sigCanvas" width="360" height="130"
                            class="w-full h-32 rounded-xl border-2 border-dashed border-slate-300 bg-slate-50 cursor-crosshair touch-none"></canvas>
                    <input type="hidden" name="signature_data" :value="sigData">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Atau Unggah File PNG Transparan:</label>
                    <input type="file" name="signature_file" accept="image/*"
                           class="w-full text-xs text-slate-600 file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:bg-emerald-50 file:text-emerald-700 file:font-semibold hover:file:bg-emerald-100">
                    <p class="mt-2 text-[11px] text-slate-500">Format PNG latar transparan disarankan agar hasil cetakan di atas garis nama tampak rapi seperti tanda tangan asli.</p>
                </div>
            </div>
        </div>

        <div class="pt-4 border-t border-slate-100">
            <h3 class="text-sm font-bold text-slate-900 mb-3">Ubah Kata Sandi (Opsional)</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Kata Sandi Baru</label>
                    <input type="password" name="password" placeholder="Kosongkan jika tidak diubah"
                           class="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Konfirmasi Kata Sandi Baru</label>
                    <input type="password" name="password_confirmation" placeholder="Ulangi kata sandi baru"
                           class="w-full rounded-xl border border-slate-300 px-3.5 py-2 text-sm">
                </div>
            </div>
        </div>

        <div class="flex justify-end">
            <button type="submit" class="px-5 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-bold shadow-2xs transition">
                Simpan Perubahan Profil
            </button>
        </div>
    </form>
</div>
@endsection
