<!DOCTYPE html>
<html lang="id" class="h-full bg-slate-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Sistem Administrasi Guru') — {{ $globalSchool->school_name ?? 'Administrasi Guru' }}</title>
    <!-- Favicon / Logo Tab Browser -->
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}?v=2">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}?v=2">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}?v=2">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}?v=2">
    <link rel="shortcut icon" href="{{ asset('favicon.ico') }}?v=2">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full font-sans text-slate-800 antialiased"
      :class="sidebarOpen ? 'overflow-hidden lg:overflow-auto' : ''"
      x-data="{
          sidebarOpen: false,
          sidebarCollapsed: localStorage.getItem('ag_sidebar_collapsed') === '1',
          toggleSidebarCollapse() {
              this.sidebarCollapsed = !this.sidebarCollapsed;
              try { localStorage.setItem('ag_sidebar_collapsed', this.sidebarCollapsed ? '1' : '0'); } catch (_) {}
          }
      }">
    <div class="min-h-screen flex flex-col lg:flex-row">
        {{-- Mobile Sidebar Overlay --}}
        <div x-show="sidebarOpen" x-cloak @click="sidebarOpen = false"
             x-transition:enter="transition-opacity ease-linear duration-200"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition-opacity ease-linear duration-200"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="fixed inset-0 z-40 bg-slate-950/75 backdrop-blur-xs lg:hidden"></div>

        {{-- Sidebar Utama (Mendukung Minimize / Expand di Desktop & Drawer Penuh di Mobile) --}}
        <aside :class="[
                   sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0',
                   sidebarCollapsed ? 'lg:w-20' : 'lg:w-72'
               ]"
               class="fixed inset-y-0 left-0 z-50 w-72 max-w-[85vw] h-screen h-dvh bg-slate-900 text-slate-100 flex flex-col transition-transform duration-200 ease-in-out shadow-2xl lg:shadow-none lg:static lg:h-auto lg:shrink-0 lg:relative">

            {{-- Tombol Floating Kecilkan / Besarkan Sidebar (Desktop) --}}
            <button type="button"
                    @click="toggleSidebarCollapse()"
                    :title="sidebarCollapsed ? 'Besarkan Sidebar' : 'Kecilkan Sidebar'"
                    class="hidden lg:flex absolute -right-3 top-6 z-50 w-6 h-6 rounded-full bg-slate-800 border border-slate-700 text-slate-300 hover:bg-emerald-600 hover:border-emerald-500 hover:text-white items-center justify-center shadow-md transition cursor-pointer">
                <svg class="w-3.5 h-3.5 transition-transform duration-200" :class="sidebarCollapsed ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"/>
                </svg>
            </button>

            <div class="py-4 border-b border-slate-800 flex items-center justify-between shrink-0"
                 :class="sidebarCollapsed ? 'px-4 lg:px-2 lg:justify-center' : 'px-4 sm:px-5'">
                <div class="flex items-center gap-3 min-w-0" :class="sidebarCollapsed ? 'lg:justify-center' : ''">
                    @php
                        $appLogo = $globalSchool?->resolvedAppLogo();
                        $hasLogo = !empty($appLogo['url'] ?? $appLogo['data_uri']);
                        $logoSrc = $appLogo['url'] ?? $appLogo['data_uri'];
                    @endphp
                    @if($hasLogo)
                        <img src="{{ $logoSrc }}" alt="Logo {{ $globalSchool->school_name ?? 'SMPN 2 Lemahabang' }}" class="w-10 h-10 object-contain rounded-xl p-0.5 bg-white/10 shrink-0">
                    @else
                        <div class="w-10 h-10 rounded-xl bg-emerald-500/20 border border-emerald-400/30 flex items-center justify-center text-emerald-300 font-extrabold text-lg shrink-0"
                             title="Administrasi Guru — {{ $globalSchool->school_name ?? 'Portal Akademik' }}">
                            AG
                        </div>
                    @endif
                    <div class="min-w-0" :class="sidebarCollapsed ? 'lg:hidden' : ''">
                        <div class="text-sm font-bold text-white truncate">Administrasi Guru</div>
                        <div class="text-xs text-slate-400 truncate">{{ $globalSchool->school_name ?? 'Portal Akademik' }}</div>
                    </div>
                </div>
                <button type="button" @click="sidebarOpen = false" class="lg:hidden p-1.5 rounded-lg text-slate-400 hover:text-white hover:bg-slate-800 transition cursor-pointer" title="Tutup Menu">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <nav class="flex-1 overflow-y-auto py-4 space-y-6" :class="sidebarCollapsed ? 'px-3 lg:px-2' : 'px-3'">
                @if(auth()->user()->isAdmin())
                <div>
                    <div class="px-3 mb-2 text-[11px] font-bold uppercase tracking-wider text-emerald-400"
                         :class="sidebarCollapsed ? 'lg:hidden' : ''">
                        Kontrol & Monitoring Admin
                    </div>
                    <div class="space-y-1">
                        <a href="{{ route('admin.dashboard') }}"
                           title="Dashboard Monitoring"
                           :class="sidebarCollapsed ? 'lg:justify-center lg:px-2' : 'px-3'"
                           class="flex items-center gap-3 py-2.5 rounded-xl text-sm font-medium transition {{ request()->routeIs('admin.dashboard') ? 'bg-emerald-600 text-white shadow-sm' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                            <span :class="sidebarCollapsed ? 'lg:hidden' : ''">Dashboard Monitoring</span>
                        </a>
                        <a href="{{ route('admin.teachers.index') }}"
                           title="Data Master Guru & Pengguna"
                           :class="sidebarCollapsed ? 'lg:justify-center lg:px-2' : 'px-3'"
                           class="flex items-center gap-3 py-2.5 rounded-xl text-sm font-medium transition {{ request()->routeIs('admin.teachers.*') ? 'bg-emerald-600 text-white shadow-sm' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                            <span :class="sidebarCollapsed ? 'lg:hidden' : ''">Master Guru</span>
                        </a>
                        <a href="{{ route('admin.kktp.index') }}"
                           title="Mapel & Standar KKM/KKTP"
                           :class="sidebarCollapsed ? 'lg:justify-center lg:px-2' : 'px-3'"
                           class="flex items-center gap-3 py-2.5 rounded-xl text-sm font-medium transition {{ request()->routeIs('admin.kktp.*') ? 'bg-emerald-600 text-white shadow-sm' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                            <span :class="sidebarCollapsed ? 'lg:hidden' : ''">Mapel & Standar KKM/KKTP</span>
                        </a>
                        <a href="{{ route('admin.classes.index') }}"
                           title="Master Rombel & Murid"
                           :class="sidebarCollapsed ? 'lg:justify-center lg:px-2' : 'px-3'"
                           class="flex items-center gap-3 py-2.5 rounded-xl text-sm font-medium transition {{ request()->routeIs('admin.classes.*') ? 'bg-emerald-600 text-white shadow-sm' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                            <span :class="sidebarCollapsed ? 'lg:hidden' : ''">Master Rombel & Murid</span>
                        </a>
                        <a href="{{ route('admin.events.index') }}"
                           title="Kalender & Hari Libur"
                           :class="sidebarCollapsed ? 'lg:justify-center lg:px-2' : 'px-3'"
                           class="flex items-center gap-3 py-2.5 rounded-xl text-sm font-medium transition {{ request()->routeIs('admin.events.*') ? 'bg-emerald-600 text-white shadow-sm' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            <span :class="sidebarCollapsed ? 'lg:hidden' : ''">Kalender & Hari Libur</span>
                        </a>
                        <a href="{{ route('admin.settings.index') }}"
                           title="Kop Sekolah & Tahun Ajaran"
                           :class="sidebarCollapsed ? 'lg:justify-center lg:px-2' : 'px-3'"
                           class="flex items-center gap-3 py-2.5 rounded-xl text-sm font-medium transition {{ request()->routeIs('admin.settings.*') ? 'bg-emerald-600 text-white shadow-sm' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                            <span :class="sidebarCollapsed ? 'lg:hidden' : ''">Kop Sekolah & Tahun Ajaran</span>
                        </a>
                        <a href="{{ route('admin.backup.index') }}"
                           title="Backup &amp; Restore Database"
                           :class="sidebarCollapsed ? 'lg:justify-center lg:px-2' : 'px-3'"
                           class="flex items-center gap-3 py-2.5 rounded-xl text-sm font-medium transition {{ request()->routeIs('admin.backup.*') ? 'bg-emerald-600 text-white shadow-sm' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"/></svg>
                            <span :class="sidebarCollapsed ? 'lg:hidden' : ''">Backup &amp; Restore DB</span>
                        </a>
                        <a href="{{ route('admin.schedules.master.index') }}"
                           title="Master Jadwal Pelajaran (Wakasek Kurikulum)"
                           :class="sidebarCollapsed ? 'lg:justify-center lg:px-2' : 'px-3'"
                           class="flex items-center gap-3 py-2.5 rounded-xl text-sm font-medium transition {{ request()->routeIs('admin.schedules.master.*') ? 'bg-emerald-600 text-white shadow-sm' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            <span :class="sidebarCollapsed ? 'lg:hidden' : ''">Master Jadwal Sekolah</span>
                        </a>
                    </div>
                </div>
                @endif

                <div>
                    <div class="px-3 mb-2 flex items-center justify-between text-[11px] font-bold uppercase tracking-wider text-slate-400"
                         :class="sidebarCollapsed ? 'lg:hidden' : ''">
                        <span>Buku Administrasi Kelas</span>
                        <span class="px-1.5 py-0.5 rounded bg-slate-800 text-emerald-400 font-semibold" title="{{ auth()->user()->isAdmin() ? 'Buku Kelas Saya yang Diampu' : 'Buku Kelas Saya' }}">{{ $quickSwitcherClasses->count() }}</span>
                    </div>
                    <div class="space-y-1">
                        <a href="{{ route('schedules.main.index') }}"
                           title="Jadwal Utama Sekolah (Jadwal Saya & Matriks 21 Rombel)"
                           :class="sidebarCollapsed ? 'lg:justify-center lg:px-2' : 'px-3'"
                           class="flex items-center gap-3 py-2.5 rounded-xl text-sm font-medium transition {{ request()->routeIs('schedules.main.*') ? 'bg-emerald-600 text-white shadow-sm' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>
                            <span :class="sidebarCollapsed ? 'lg:hidden' : ''">Jadwal Utama</span>
                        </a>
                        <a href="{{ route('classes.index') }}"
                           title="Buku Administrasi Kelas {{ $quickSwitcherClasses->isNotEmpty() ? '(' . $quickSwitcherClasses->count() . ' Kelas)' : '' }}"
                           :class="sidebarCollapsed ? 'lg:justify-center lg:px-2' : 'px-3'"
                           class="flex items-center gap-3 py-2.5 rounded-xl text-sm font-medium transition {{ request()->routeIs('classes.index') ? 'bg-emerald-600 text-white shadow-sm' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                            <span :class="sidebarCollapsed ? 'lg:hidden' : ''">Buku Administrasi Kelas</span>
                        </a>
                        <a href="{{ route('schedules.index') }}"
                           title="Jadwal Hari Ini"
                           :class="sidebarCollapsed ? 'lg:justify-center lg:px-2' : 'px-3'"
                           class="flex items-center gap-3 py-2.5 rounded-xl text-sm font-medium transition {{ request()->routeIs('schedules.index') ? 'bg-emerald-600 text-white shadow-sm' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <span :class="sidebarCollapsed ? 'lg:hidden' : ''">Jadwal Hari Ini</span>
                        </a>
                    </div>

                    @if($quickSwitcherClasses->isNotEmpty())
                    <div class="mt-3 pt-3 border-t border-slate-800/80">
                        <div class="px-3 mb-1.5 text-[11px] text-slate-400" :class="sidebarCollapsed ? 'lg:hidden' : ''">
                            Akses Cepat Kelas ({{ $selectedAcademicYear->semester }}):
                        </div>
                        <div class="space-y-1" :class="sidebarCollapsed ? 'lg:max-h-64 lg:overflow-y-auto lg:pr-0' : 'pr-1'">
                            @php
                                $activeClassId = isset($teachingClass) ? $teachingClass->id : null;
                                $currentSubMenu = 'journals.index';
                                if (request()->routeIs('attendances.*')) {
                                    $currentSubMenu = 'attendances.index';
                                } elseif (request()->routeIs('grades.*')) {
                                    $currentSubMenu = 'grades.index';
                                } elseif (request()->routeIs('classes.students*')) {
                                    $currentSubMenu = 'classes.students';
                                }
                            @endphp
                            @foreach($quickSwitcherClasses as $qCls)
                                <a href="{{ route($currentSubMenu, $qCls) }}"
                                   title="{{ $qCls->class_name }} — {{ $qCls->subject->name ?? $qCls->subject->code ?? '' }}"
                                   :class="sidebarCollapsed ? 'lg:justify-center lg:px-1.5' : 'justify-between px-3'"
                                   class="flex items-center py-1.5 rounded-lg text-xs transition {{ $activeClassId === $qCls->id ? 'bg-slate-800 text-emerald-300 font-semibold border border-emerald-500/30' : 'text-slate-400 hover:bg-slate-800/60 hover:text-slate-200' }}">
                                    <span class="truncate" :class="sidebarCollapsed ? 'lg:hidden' : ''">
                                        <span class="font-bold text-slate-200">{{ $qCls->class_name }}</span>
                                        <span class="text-slate-400">• {{ $qCls->subject->code ?? $qCls->subject->name ?? '' }}</span>
                                    </span>
                                    <span class="hidden font-extrabold text-[11px] text-slate-200 truncate"
                                          :class="sidebarCollapsed ? 'lg:inline' : ''">
                                        {{ $qCls->class_name }}
                                    </span>
                                    @if(auth()->user()->isAdmin())
                                        <span class="text-[10px] text-slate-500 truncate max-w-[70px]" :class="sidebarCollapsed ? 'lg:hidden' : ''">{{ strtok($qCls->teacher->name ?? '', ' ') }}</span>
                                    @endif
                                </a>
                            @endforeach
                        </div>
                    </div>
                    @elseif(auth()->user()->isAdmin())
                    <div class="mt-3 pt-3 border-t border-slate-800/80 px-1" :class="sidebarCollapsed ? 'lg:hidden' : ''">
                        <a href="{{ route('classes.index', ['view' => 'all']) }}"
                           class="group flex items-center justify-between p-2.5 rounded-xl bg-slate-800/60 hover:bg-slate-800 border border-slate-700/50 hover:border-slate-600 transition">
                            <div class="flex items-center gap-2.5 min-w-0">
                                <div class="w-7 h-7 rounded-lg bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-emerald-400 shrink-0">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                                </div>
                                <div class="text-left truncate">
                                    <p class="text-xs font-semibold text-slate-200 truncate group-hover:text-emerald-300">Supervisi &amp; Filter Buku Guru</p>
                                    <p class="text-[10px] text-slate-400 truncate">Total {{ $totalSchoolClassesCount ?? 0 }} buku kelas KBM</p>
                                </div>
                            </div>
                            <svg class="w-3.5 h-3.5 text-slate-400 group-hover:translate-x-0.5 group-hover:text-emerald-300 transition shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </a>
                    </div>
                    @endif
                </div>
            </nav>

            <div class="p-3.5 sm:p-4 border-t border-slate-800 bg-slate-950/70 shrink-0" :class="sidebarCollapsed ? 'lg:px-2 lg:py-3' : ''">
                <div class="flex items-center justify-between gap-2" :class="sidebarCollapsed ? 'lg:flex-col lg:justify-center' : ''">
                    <a href="{{ route('profile.edit') }}"
                       title="Profil & TTD: {{ auth()->user()->name }}"
                       class="flex items-center gap-2.5 min-w-0 group"
                       :class="sidebarCollapsed ? 'lg:justify-center' : ''">
                        <div class="w-9 h-9 rounded-full bg-emerald-600/30 border border-emerald-500/40 flex items-center justify-center text-emerald-300 font-bold text-sm shrink-0">
                            {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                        </div>
                        <div class="min-w-0" :class="sidebarCollapsed ? 'lg:hidden' : ''">
                            <div class="text-xs font-semibold text-white truncate group-hover:text-emerald-300">{{ auth()->user()->name }}</div>
                            <div class="text-[11px] text-slate-400 truncate">
                                {{ auth()->user()->isAdmin() ? 'Administrator' : ('NIP: ' . (auth()->user()->nip ?: '-')) }}
                            </div>
                        </div>
                    </a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" title="Keluar"
                                class="p-2 rounded-lg text-slate-400 hover:text-rose-400 hover:bg-slate-800 transition">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                        </button>
                    </form>
                </div>
            </div>
        </aside>

        {{-- Konten Utama --}}
        <div class="flex-1 flex flex-col min-w-0">
            {{-- Topbar --}}
            <header class="sticky top-0 z-30 bg-white/95 backdrop-blur border-b border-slate-200 px-3 sm:px-6 py-2.5 sm:py-3">
                <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-2.5 sm:gap-3">
                    <div class="flex items-center justify-between lg:justify-start gap-2 sm:gap-3 min-w-0">
                        <div class="flex items-center gap-2 sm:gap-3 min-w-0 flex-1">
                            {{-- Tombol Buka Sidebar (Mobile) --}}
                            <button type="button" @click="sidebarOpen = true" class="lg:hidden p-2 rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-100 shrink-0" title="Buka Menu">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                            </button>
                            {{-- Tombol Kecilkan / Besarkan Sidebar (Desktop) --}}
                            <button type="button"
                                    @click="toggleSidebarCollapse()"
                                    :title="sidebarCollapsed ? 'Besarkan Sidebar' : 'Kecilkan Sidebar'"
                                    class="hidden lg:inline-flex p-2 rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-100 hover:text-slate-900 transition cursor-pointer shrink-0">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <rect x="3" y="4" width="18" height="16" rx="2" stroke-width="1.8"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 4v16"/>
                                </svg>
                            </button>
                            <div class="min-w-0">
                                <h1 class="text-sm sm:text-lg font-bold text-slate-900 leading-tight truncate">@yield('page_title', 'Dashboard Administrasi Guru')</h1>
                                @hasSection('page_subtitle')
                                    <p class="text-[11px] sm:text-xs text-slate-500 truncate">@yield('page_subtitle')</p>
                                @endif
                            </div>
                        </div>

                        {{-- Mobile-only right icons: Clock & Profile --}}
                        <div class="flex lg:hidden items-center gap-1.5 shrink-0">
                            {{-- Clock (Mobile compact) --}}
                            <div x-data="{
                                    now: new Date(),
                                    timer: null,
                                    init() {
                                        this.updateNow();
                                        this.timer = setInterval(() => { this.updateNow(); }, 1000);
                                    },
                                    destroy() {
                                        if (this.timer) clearInterval(this.timer);
                                    },
                                    updateNow() {
                                        this.now = new Date();
                                    },
                                    get timeFormatted() {
                                        const pad = (n) => String(n).padStart(2, '0');
                                        return `${pad(this.now.getHours())}:${pad(this.now.getMinutes())}:${pad(this.now.getSeconds())}`;
                                    }
                                 }"
                                 title='Waktu Sistem Realtime (WIB)'
                                 class="inline-flex items-center gap-1 px-2 py-1 rounded-lg border border-slate-200 bg-slate-50 text-slate-700 select-none">
                                <span class="relative flex h-1.5 w-1.5">
                                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                                    <span class="relative inline-flex rounded-full h-1.5 w-1.5 bg-emerald-500"></span>
                                </span>
                                <span class="text-xs font-black font-mono tracking-tight text-slate-900" x-text="timeFormatted"></span>
                            </div>

                            <a href="{{ route('profile.edit') }}"
                               class="p-1.5 rounded-lg border border-slate-200 bg-white text-slate-700 hover:bg-slate-50 shrink-0"
                               title="Profil & Tanda Tangan">
                                <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                            </a>
                        </div>
                    </div>

                    {{-- Actions & Switchers: Desktop inline, Mobile secondary row or flex --}}
                    <div class="flex flex-wrap lg:flex-nowrap items-center justify-between lg:justify-end gap-2 sm:gap-2.5">
                        {{-- Quick Class Switcher Dropdown bila sedang membuka suatu kelas --}}
                        @if(isset($teachingClass) && $quickSwitcherClasses->count() > 1)
                            <div class="flex items-center gap-1.5 flex-1 sm:flex-initial min-w-0">
                                <span class="hidden sm:inline text-xs font-medium text-slate-500 shrink-0">Pindah Kelas:</span>
                                <select data-prev-value="{{ route($currentSubMenu, $teachingClass) }}"
                                        onchange="window.handleGuardedSelectNavigate(this, 'href')"
                                        class="w-full sm:w-auto text-xs font-semibold rounded-lg border-slate-300 bg-slate-50 py-1.5 pl-2.5 pr-7 text-slate-800 focus:border-emerald-500 focus:ring-emerald-500 truncate">
                                    @foreach($quickSwitcherClasses as $qCls)
                                        <option value="{{ route($currentSubMenu, $qCls) }}" @selected($qCls->id === $teachingClass->id)>
                                            {{ $qCls->class_name }} — {{ $qCls->subject->name ?? '' }} {{ auth()->user()->isAdmin() ? '(' . strtok($qCls->teacher->name ?? '', ' ') . ')' : '' }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        @endif

                        {{-- Pemilih Tahun Ajaran & Semester --}}
                        <form method="POST" action="{{ route('academic-year.switch') }}" data-skip-dirty-guard="1" class="flex items-center flex-1 sm:flex-initial min-w-0">
                            @csrf
                            <select name="academic_year_id"
                                    data-prev-value="{{ $selectedAcademicYear->id }}"
                                    onchange="window.handleGuardedSelectNavigate(this, 'submit')"
                                    class="w-full sm:w-auto text-xs font-semibold rounded-lg border border-emerald-200 bg-emerald-50/70 py-1.5 pl-2.5 pr-7 text-emerald-900 focus:border-emerald-500 focus:ring-emerald-500 truncate">
                                @foreach($globalAcademicYears as $ay)
                                    <option value="{{ $ay->id }}" @selected($ay->id === $selectedAcademicYear->id)>
                                        TA {{ $ay->year_name }} • {{ $ay->semester }} {{ $ay->is_active ? '(Aktif)' : '(Arsip)' }}
                                    </option>
                                @endforeach
                            </select>
                        </form>

                        {{-- Desktop-only Profile & Clock --}}
                        <div class="hidden lg:flex items-center gap-2.5 shrink-0">
                            <a href="{{ route('profile.edit') }}"
                               class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-slate-200 bg-white text-xs font-semibold text-slate-700 hover:bg-slate-50">
                                <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                <span>Profil & TTD</span>
                            </a>

                            {{-- Widget Tanggal & Jam Realtime di Pojok Kanan Atas (Desktop) --}}
                            <div x-data="{
                                    now: new Date(),
                                    timer: null,
                                    init() {
                                        this.updateNow();
                                        this.timer = setInterval(() => { this.updateNow(); }, 1000);
                                    },
                                    destroy() {
                                        if (this.timer) clearInterval(this.timer);
                                    },
                                    updateNow() {
                                        this.now = new Date();
                                    },
                                    get dayName() {
                                        const days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
                                        return days[this.now.getDay()];
                                    },
                                    get dateFormatted() {
                                        const months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
                                        return `${this.dayName}, ${this.now.getDate()} ${months[this.now.getMonth()]} ${this.now.getFullYear()}`;
                                    },
                                    get timeFormatted() {
                                        const pad = (n) => String(n).padStart(2, '0');
                                        return `${pad(this.now.getHours())}:${pad(this.now.getMinutes())}:${pad(this.now.getSeconds())} WIB`;
                                    }
                                 }"
                                 title='Waktu Sistem Realtime (WIB)'
                                 class="inline-flex items-center gap-2 px-2.5 sm:px-3 py-1 rounded-xl border border-slate-200 bg-slate-50/90 text-slate-700 select-none shadow-2xs">
                                <div class="flex items-center justify-center shrink-0">
                                    <span class="relative flex h-2 w-2">
                                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                                        <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                                    </span>
                                </div>
                                <div class="flex flex-col text-left leading-tight">
                                    <span class="text-[10px] font-semibold text-slate-500 truncate" x-text="dateFormatted"></span>
                                    <span class="text-xs font-black font-mono tracking-tight text-slate-900" x-text="timeFormatted"></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            {{-- Flash Alerts --}}
            <div class="px-4 sm:px-6 pt-4 space-y-2">
                @if(session('success'))
                    <div x-data="{ show: true }" x-show="show"
                         class="flex items-center justify-between gap-3 rounded-xl bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm text-emerald-900 shadow-2xs">
                        <div class="flex items-center gap-2.5">
                            <svg class="w-5 h-5 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <span class="font-medium">{{ session('success') }}</span>
                        </div>
                        <button @click="show = false" class="text-emerald-600 hover:text-emerald-900">&times;</button>
                    </div>
                @endif

                @if($errors->any())
                    <div class="rounded-xl bg-rose-50 border border-rose-200 px-4 py-3 text-sm text-rose-900">
                        <div class="font-bold mb-1">Mohon periksa kembali isian berikut:</div>
                        <ul class="list-disc list-inside text-xs space-y-0.5">
                            @foreach($errors->all() as $err)
                                <li>{{ $err }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>

            <main class="flex-1 px-4 sm:px-6 py-4">
                @yield('content')
            </main>

            <footer class="px-6 py-3 border-t border-slate-200 bg-white text-xs text-slate-500 flex flex-wrap items-center justify-between gap-2">
                <span>&copy; {{ date('Y') }} Sistem Administrasi Guru Terpadu — {{ $globalSchool->school_name }}</span>
                <span>Standar Dokumen Supervisi Akademik (Jurnal Harian • Absensi • Daftar Nilai & KKTP)</span>
            </footer>
        </div>
    </div>
    <script>
        window.__workspaceDirty = false;
        window.__workspaceDirtyLabel = 'Absensi / Nilai';
        window.__workspaceSaveFn = null;

        window.confirmWorkspaceDiscard = function () {
            if (!window.__workspaceDirty) return true;
            const label = window.__workspaceDirtyLabel || 'data';
            const proceed = window.confirm(
                `Perhatian: Ada perubahan ${label} yang BELUM DISIMPAN.\n\n` +
                `• Klik "Batal" untuk tetap di halaman ini lalu tekan tombol Simpan.\n` +
                `• Klik "OK" jika ingin keluar dan mengabaikan perubahan yang belum disimpan.`
            );
            if (proceed) {
                window.__workspaceDirty = false;
                window.__gradeDirty = false;
            }
            return proceed;
        };

        window.handleGuardedSelectNavigate = function (el, mode) {
            if (!window.confirmWorkspaceDiscard()) {
                const prev = el.getAttribute('data-prev-value');
                if (prev !== null) {
                    el.value = prev;
                } else if (typeof el.defaultValue !== 'undefined') {
                    el.value = el.defaultValue;
                }
                return false;
            }
            el.setAttribute('data-prev-value', el.value);
            if (mode === 'href' && el.value) {
                window.location.href = el.value;
            } else if (mode === 'submit' && el.form) {
                el.form.submit();
            }
            return true;
        };

        window.addEventListener('beforeunload', function (e) {
            if (window.__workspaceDirty) {
                e.preventDefault();
                e.returnValue = '';
            }
        });

        document.addEventListener('click', function (e) {
            if (!window.__workspaceDirty || e.defaultPrevented || e.button !== 0) return;
            if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
            const link = e.target && typeof e.target.closest === 'function' ? e.target.closest('a[href]') : null;
            if (!link) return;
            const href = (link.getAttribute('href') || '').trim();
            if (!href || href.startsWith('#') || href.startsWith('javascript:')) return;
            if (link.target === '_blank' || link.hasAttribute('download') || link.dataset.skipDirtyGuard === '1') return;

            if (!window.confirmWorkspaceDiscard()) {
                e.preventDefault();
                e.stopPropagation();
            }
        }, true);

        document.addEventListener('submit', function (e) {
            if (!window.__workspaceDirty || e.defaultPrevented) return;
            const form = e.target;
            if (!form || form.target === '_blank' || form.dataset.skipDirtyGuard === '1') return;

            if (!window.confirmWorkspaceDiscard()) {
                e.preventDefault();
                e.stopPropagation();
            }
        }, true);
    </script>
    @stack('scripts')
</body>
</html>
