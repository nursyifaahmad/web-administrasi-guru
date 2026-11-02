<div x-show="nicknameModal" x-cloak
     class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
    <div @click.away="nicknameModal = false"
         class="bg-white rounded-2xl border border-slate-200 shadow-xl max-w-lg w-full max-h-[90vh] flex flex-col overflow-hidden">
        <div class="px-5 py-4 bg-slate-900 text-white flex items-center justify-between shrink-0">
            <div>
                <h3 class="text-sm font-bold">Atur Nama Panggilan Peserta Didik</h3>
                <p class="text-[11px] text-slate-300">Kelas {{ $teachingClass->class_name }} • Disimpan ke rombel & terhubung otomatis ke guru lain</p>
            </div>
            <button type="button" @click="nicknameModal = false" class="text-slate-400 hover:text-white text-xl leading-none">&times;</button>
        </div>

        <form method="POST" action="{{ route('classes.nicknames.update', $teachingClass) }}" class="flex flex-col flex-1 overflow-hidden">
            @csrf
            <div class="p-3 bg-amber-50 border-b border-amber-200 text-amber-900 text-xs shrink-0 flex items-center gap-2">
                <span>💡</span>
                <span>Tekan tombol <strong>Enter</strong> pada keyboard untuk langsung berpindah ke murid di baris berikutnya.</span>
            </div>

            <div class="p-4 overflow-y-auto flex-1 space-y-2 text-xs">
                <table class="w-full text-left">
                    <thead class="text-[11px] text-slate-500 uppercase border-b border-slate-200">
                        <tr>
                            <th class="py-1.5 px-2 w-10 text-center">No</th>
                            <th class="py-1.5 px-2">Nama Lengkap</th>
                            <th class="py-1.5 px-2 w-44">Nama Panggilan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($students as $idx => $st)
                            <tr class="hover:bg-slate-50/60">
                                <td class="py-1.5 px-2 text-center font-bold text-slate-500">{{ $st->order_number ?? $idx + 1 }}</td>
                                <td class="py-1.5 px-2 font-semibold text-slate-800 text-[11px] truncate max-w-[180px]">{{ $st->full_name }}</td>
                                <td class="py-1.5 px-2">
                                    <input type="text"
                                           name="nicknames[{{ $st->id }}]"
                                           value="{{ $st->nickname }}"
                                           placeholder="Panggilan..."
                                           id="nick-input-{{ $idx }}"
                                           @keydown.enter.prevent="document.getElementById('nick-input-{{ $idx + 1 }}')?.focus()"
                                           class="w-full rounded-lg border border-slate-300 px-2.5 py-1 text-xs focus:border-emerald-500 focus:ring-emerald-500 font-bold text-slate-900">
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="px-5 py-3.5 bg-slate-50 border-t border-slate-200 flex items-center justify-between shrink-0">
                <button type="button" @click="nicknameModal = false"
                        class="px-4 py-2 rounded-xl border border-slate-300 font-semibold text-xs hover:bg-white">Batal</button>
                <button type="submit"
                        class="px-5 py-2 rounded-xl bg-emerald-600 text-white font-bold text-xs hover:bg-emerald-700 shadow-2xs transition">
                    Simpan Nama Panggilan
                </button>
            </div>
        </form>
    </div>
</div>
