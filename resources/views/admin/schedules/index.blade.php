@extends('layouts.app')

@section('title', 'Master Jadwal Pelajaran Sekolah')
@section('page_title', 'Master Jadwal Pelajaran Sekolah')
@section('page_subtitle', 'Pengaturan jadwal KBM ' . ($matrixData['total_classes'] ?? 21) . ' rombel, deteksi bentrok otomatis, dan sinkronisasi ke administrasi guru.')

@section('content')
@php
    $matrixByDay = $matrixData['matrix_by_day'] ?? [];
    $isTwoShift = (bool) ($matrixData['is_two_shift'] ?? false);
    $shift1Meta = $matrixData['shift1_meta'] ?? [];
    $shift2Meta = $matrixData['shift2_meta'] ?? [];
    $daysMap = \App\Services\SchoolScheduleService::DAYS_MAP;

    $cellsMap = [];
    foreach ($matrixByDay as $dNum => $dData) {
        foreach (['pagi_slots', 'siang_slots'] as $slotKey) {
            foreach (($dData[$slotKey] ?? []) as $slot) {
                $isSlotAct = (bool) ($slot['is_activity'] ?? false);
                foreach (($slot['classes'] ?? []) as $item) {
                    if (! $item || empty($item->id)) {
                        continue;
                    }
                    $cellsMap[(int) $item->id] = [
                        'id' => (int) $item->id,
                        'day_of_week' => (int) $item->day_of_week,
                        'day_name' => $daysMap[(int) $item->day_of_week] ?? ('Hari ' . $item->day_of_week),
                        'shift' => (string) $item->shift,
                        'period_order' => (int) $item->period_order,
                        'time_range' => (string) $item->time_range,
                        'class_name' => (string) $item->class_name,
                        'teacher_code' => $item->teacher_code !== null ? (string) $item->teacher_code : null,
                        'teacher_name' => $item->teacher_name !== null ? (string) $item->teacher_name : null,
                        'subject_name' => $item->subject_name !== null ? (string) $item->subject_name : null,
                        'user_id' => $item->user_id ? (int) $item->user_id : null,
                        'subject_id' => $item->subject_id ? (int) $item->subject_id : null,
                        'is_conflict' => (bool) $item->is_conflict,
                        'conflict_notes' => $item->conflict_notes ? (string) $item->conflict_notes : null,
                        'is_activity' => $isSlotAct || $item->isCommonActivity(),
                    ];
                }
            }
        }
    }

    $boardConfig = [
        'teachers' => $teachers->values()->all(),
        'cellsMap' => $cellsMap,
        'conflicts' => array_values($conflicts),
        'activeDay' => $activeDay === 'all' ? 'all' : (string) $activeDay,
        'swapUrl' => route('admin.schedules.master.swap'),
        'swapTargetsUrl' => route('admin.schedules.master.swap-targets'),
        'csrfToken' => csrf_token(),
    ];
@endphp

<script>
    window.masterScheduleBoard = function (cfg) {
        const ACTIVITY_KEYWORDS = [
            'UPACARA', 'SHOLAT', 'DHUHA', 'ISTIRAHAT', 'KEAGAMAAN',
            'SABTU BERSIH', 'SABSIH', 'PAGI CERIA', 'PEMBIASAAN', 'LITERASI', 'EKSTRAKURIKULER'
        ];

        return {
            createChoiceModal: false,
            createChoiceStep: 'choose',
            editModal: false,
            swapModal: false,
            importScheduleModal: false,
            selectedItem: null,
            selectedTeacherCode: '',
            selectedTeacherName: '',
            selectedSubjectName: '',
            swapItem1: null,
            swapItem2Id: '',
            swapTargetSlots: [],
            loadingSwapTargets: false,
            swapFilterDay: 'all',

            teachersList: cfg.teachers || [],
            cellsMap: cfg.cellsMap || {},
            conflictsList: cfg.conflicts || [],
            activeDayFilter: cfg.activeDay || 'all',
            conflictsExpanded: false,
            openLegend: true,
            teacherSearch: '',
            highlightedTeacherCode: '',
            quickSwapMode: false,

            activeSourceId: null,
            targetCleanMap: {},
            cleanTargetsCount: 0,
            hasUnsyncedSwaps: false,
            isSwapping: false,
            lastSwapUndo: null,
            toast: null,
            toastTimer: null,
            pulseCellId: null,

            dragState: {
                active: false,
                sourceId: null,
                overTargetId: null,
                x: 0,
                y: 0,
            },
            pointerCandidate: null,
            suppressNextClickUntil: 0,
            autoScrollInterval: null,

            normCode(code) {
                return String(code || '').trim().replace(/\s+/g, '').toUpperCase();
            },

            isActivityText(code) {
                if (!code) return false;
                const upper = String(code).trim().toUpperCase();
                return ACTIVITY_KEYWORDS.some((kw) => upper.includes(kw));
            },

            isCommonActivity(item) {
                return !!(item && (item.is_activity || this.isActivityText(item.teacher_code)));
            },

            get activeSourceCell() {
                if (!this.activeSourceId) return null;
                return this.cellsMap[this.activeSourceId] || null;
            },

            get activeTargetPreviewCell() {
                const tid = this.dragState.active ? this.dragState.overTargetId : null;
                return tid ? (this.cellsMap[tid] || null) : null;
            },

            get filteredTeachersLegend() {
                const q = this.teacherSearch.trim().toLowerCase();
                if (!q) return this.teachersList;
                return this.teachersList.filter((t) => {
                    return String(t.code || '').toLowerCase().includes(q)
                        || String(t.name || '').toLowerCase().includes(q)
                        || String(t.subject || '').toLowerCase().includes(q);
                });
            },

            dayConflictsCount(dayNum) {
                return this.conflictsList.filter((c) => Number(c.day_of_week) === Number(dayNum)).length;
            },

            setDayFilter(val) {
                this.activeDayFilter = String(val);
            },

            toggleTeacherHighlight(code) {
                const norm = this.normCode(code);
                this.highlightedTeacherCode = (this.highlightedTeacherCode === norm) ? '' : norm;
            },

            showToast(type, message) {
                this.toast = { type, message };
                if (this.toastTimer) clearTimeout(this.toastTimer);
                this.toastTimer = setTimeout(() => {
                    this.toast = null;
                }, 6500);
            },

            openEdit(item) {
                if (!item) return;
                this.selectedItem = item;
                this.selectedTeacherCode = item.teacher_code || '';
                this.selectedTeacherName = item.teacher_name || '';
                this.selectedSubjectName = item.subject_name || '';
                this.editModal = true;
            },

            onTeacherSelect(code) {
                this.selectedTeacherCode = code;
                const t = this.teachersList.find((x) => String(x.code) === String(code));
                if (t) {
                    this.selectedTeacherName = t.name;
                    this.selectedSubjectName = t.subject;
                } else if (code === '') {
                    this.selectedTeacherName = '';
                    this.selectedSubjectName = '';
                }
            },

            async openSwap(item) {
                if (!item) return;
                this.swapItem1 = item;
                this.swapItem2Id = '';
                this.swapTargetSlots = [];
                this.loadingSwapTargets = true;
                this.swapFilterDay = 'all';
                this.swapModal = true;

                try {
                    const resp = await fetch(cfg.swapTargetsUrl + '?item_id=' + item.id, {
                        headers: { 'Accept': 'application/json' }
                    });
                    const data = await resp.json();
                    if (data.success && data.slots) {
                        this.swapTargetSlots = data.slots;
                    }
                } catch (err) {
                    console.error('Gagal memuat slot target penukaran:', err);
                } finally {
                    this.loadingSwapTargets = false;
                }
            },

            get filteredSwapTargets() {
                if (this.swapFilterDay === 'all') {
                    return this.swapTargetSlots;
                }
                return this.swapTargetSlots.filter((s) => String(s.day_of_week) === String(this.swapFilterDay));
            },

            computeTargetStatusMap(sourceCell) {
                const map = {};
                let cleanCount = 0;
                if (!sourceCell) {
                    this.targetCleanMap = map;
                    this.cleanTargetsCount = 0;
                    return;
                }

                const bySlot = {};
                const all = Object.values(this.cellsMap);
                for (let i = 0; i < all.length; i++) {
                    const c = all[i];
                    const sk = c.day_of_week + '_' + c.shift + '_' + c.time_range;
                    if (!bySlot[sk]) bySlot[sk] = [];
                    bySlot[sk].push(c);
                }

                const srcSlotKey = sourceCell.day_of_week + '_' + sourceCell.shift + '_' + sourceCell.time_range;
                const srcCode = !sourceCell.is_activity ? this.normCode(sourceCell.teacher_code) : '';

                for (let i = 0; i < all.length; i++) {
                    const target = all[i];
                    if (target.id === sourceCell.id || target.class_name !== sourceCell.class_name || target.is_activity) {
                        continue;
                    }

                    const dstSlotKey = target.day_of_week + '_' + target.shift + '_' + target.time_range;
                    const dstCode = !target.is_activity ? this.normCode(target.teacher_code) : '';

                    let srcClash = false;
                    if (srcCode !== '') {
                        const dstSiblings = bySlot[dstSlotKey] || [];
                        for (let j = 0; j < dstSiblings.length; j++) {
                            const sib = dstSiblings[j];
                            if (sib.id === sourceCell.id || sib.id === target.id || sib.is_activity) continue;
                            if (this.normCode(sib.teacher_code) === srcCode) {
                                srcClash = true;
                                break;
                            }
                        }
                    }

                    let dstClash = false;
                    if (dstCode !== '') {
                        const srcSiblings = bySlot[srcSlotKey] || [];
                        for (let j = 0; j < srcSiblings.length; j++) {
                            const sib = srcSiblings[j];
                            if (sib.id === sourceCell.id || sib.id === target.id || sib.is_activity) continue;
                            if (this.normCode(sib.teacher_code) === dstCode) {
                                dstClash = true;
                                break;
                            }
                        }
                    }

                    const isClean = !srcClash && !dstClash;
                    map[target.id] = isClean;
                    if (isClean) cleanCount++;
                }

                this.targetCleanMap = map;
                this.cleanTargetsCount = cleanCount;
            },

            selectSwapSource(cell) {
                if (!cell || cell.is_activity) return;
                this.activeSourceId = cell.id;
                this.computeTargetStatusMap(cell);
            },

            clearSwapSelection() {
                this.activeSourceId = null;
                this.targetCleanMap = {};
                this.cleanTargetsCount = 0;
            },

            isSameClassValidTarget(targetId) {
                const src = this.activeSourceCell;
                const tgt = this.cellsMap[targetId];
                if (!src || !tgt) return false;
                return tgt.id !== src.id && tgt.class_name === src.class_name && !tgt.is_activity;
            },

            focusConflictCell(itemId, selectImmediately = false) {
                const cell = this.cellsMap[itemId];
                if (!cell) return;
                this.activeDayFilter = 'all';
                if (selectImmediately) {
                    this.selectSwapSource(cell);
                }
                this.pulseCellId = cell.id;
                setTimeout(() => {
                    if (this.pulseCellId === cell.id) this.pulseCellId = null;
                }, 1800);

                this.$nextTick(() => {
                    const el = document.getElementById('schedule-cell-' + cell.id);
                    if (el) {
                        el.scrollIntoView({ behavior: 'smooth', block: 'center', inline: 'center' });
                    }
                });
            },

            onCellClick(id) {
                if (Date.now() < this.suppressNextClickUntil || this.isSwapping) {
                    return;
                }
                const cell = this.cellsMap[id];
                if (!cell || cell.is_activity) {
                    return;
                }

                const src = this.activeSourceCell;
                if (src) {
                    if (cell.id === src.id) {
                        this.clearSwapSelection();
                        return;
                    }
                    if (cell.class_name === src.class_name) {
                        this.executeSwap(src.id, cell.id);
                        return;
                    }
                    this.showToast('warning', 'Penukaran hanya dapat dilakukan pada kolom Kelas ' + src.class_name + '. Klik Batal (Esc) untuk memilih kelas lain.');
                    return;
                }

                if (cell.is_conflict || this.quickSwapMode) {
                    this.selectSwapSource(cell);
                    return;
                }

                this.openEdit(cell);
            },

            onCellPointerDown(e, id) {
                if (this.isSwapping) return;
                if (e.pointerType === 'mouse' && e.button !== 0) return;

                const cell = this.cellsMap[id];
                if (!cell || cell.is_activity) return;

                this.cancelPointerCandidate();

                const holdMs = e.pointerType === 'touch' ? 220 : 170;
                const cand = {
                    id: cell.id,
                    pointerId: e.pointerId,
                    pointerType: e.pointerType,
                    startX: e.clientX,
                    startY: e.clientY,
                    curX: e.clientX,
                    curY: e.clientY,
                    timer: null,
                };

                cand.timer = setTimeout(() => {
                    if (this.pointerCandidate && this.pointerCandidate.id === cand.id) {
                        this.beginDrag(cand.id, cand.curX, cand.curY);
                    }
                }, holdMs);

                this.pointerCandidate = cand;

                window.addEventListener('pointermove', this._boundPointerMove, { passive: true });
                window.addEventListener('pointerup', this._boundPointerUp);
                window.addEventListener('pointercancel', this._boundPointerUp);
            },

            handleGlobalPointerMove(e) {
                if (this.dragState.active) {
                    this.dragState.x = e.clientX;
                    this.dragState.y = e.clientY;
                    this.updateDragTargetAt(e.clientX, e.clientY);
                    return;
                }

                const cand = this.pointerCandidate;
                if (!cand || e.pointerId !== cand.pointerId) return;

                cand.curX = e.clientX;
                cand.curY = e.clientY;
                const dist = Math.hypot(e.clientX - cand.startX, e.clientY - cand.startY);

                if (cand.pointerType === 'touch' && dist >= 10) {
                    // Biarkan scroll layar sentuh berjalan normal jika jari digeser sebelum hold threshold
                    this.cancelPointerCandidate();
                    return;
                }

                if (cand.pointerType === 'mouse' && dist >= 6) {
                    this.beginDrag(cand.id, e.clientX, e.clientY);
                }
            },

            handleGlobalPointerUp(e) {
                if (this.dragState.active) {
                    const srcId = this.dragState.sourceId;
                    const dstId = this.dragState.overTargetId;
                    const srcCell = this.cellsMap[srcId];

                    this.suppressNextClickUntil = Date.now() + 320;
                    this.endDragCleanup();

                    if (srcId && dstId && dstId !== srcId) {
                        this.executeSwap(srcId, dstId);
                    } else if (srcCell) {
                        const under = document.elementFromPoint(e.clientX, e.clientY);
                        const btn = under ? under.closest('[data-schedule-cell-id]') : null;
                        const underId = btn ? Number(btn.getAttribute('data-schedule-cell-id')) : null;
                        const underCell = underId ? this.cellsMap[underId] : null;
                        if (underCell && underCell.id !== srcCell.id && underCell.class_name !== srcCell.class_name) {
                            this.showToast('warning', 'Lepaskan di sel berwarna hijau/kuning dalam kolom Kelas ' + srcCell.class_name + ' untuk menukar jadwal.');
                        }
                    }
                    return;
                }

                this.cancelPointerCandidate();
            },

            beginDrag(sourceId, x, y) {
                const cell = this.cellsMap[sourceId];
                if (!cell || cell.is_activity) {
                    this.cancelPointerCandidate();
                    return;
                }
                if (this.pointerCandidate && this.pointerCandidate.timer) {
                    clearTimeout(this.pointerCandidate.timer);
                }
                this.pointerCandidate = null;

                this.activeSourceId = sourceId;
                this.computeTargetStatusMap(cell);

                this.dragState.active = true;
                this.dragState.sourceId = sourceId;
                this.dragState.overTargetId = null;
                this.dragState.x = x;
                this.dragState.y = y;

                document.body.style.userSelect = 'none';
                this.startAutoScrollLoop();
                this.updateDragTargetAt(x, y);
            },

            updateDragTargetAt(x, y) {
                const el = document.elementFromPoint(x, y);
                const btn = el ? el.closest('[data-schedule-cell-id]') : null;
                const tid = btn ? Number(btn.getAttribute('data-schedule-cell-id')) : null;
                if (tid && this.isSameClassValidTarget(tid)) {
                    this.dragState.overTargetId = tid;
                } else {
                    this.dragState.overTargetId = null;
                }
            },

            startAutoScrollLoop() {
                if (this.autoScrollInterval) clearInterval(this.autoScrollInterval);
                this.autoScrollInterval = setInterval(() => {
                    if (!this.dragState.active) return;
                    const x = this.dragState.x;
                    const y = this.dragState.y;
                    const margin = 72;

                    if (y < margin) {
                        window.scrollBy(0, -14);
                    } else if (window.innerHeight - y < margin) {
                        window.scrollBy(0, 14);
                    }

                    const containers = document.querySelectorAll('[data-matrix-scroll]');
                    containers.forEach((box) => {
                        if (box.scrollWidth <= box.clientWidth) return;
                        const rect = box.getBoundingClientRect();
                        if (x < rect.left || x > rect.right || y < rect.top || y > rect.bottom) return;
                        if (x - rect.left < 72) box.scrollLeft -= 14;
                        else if (rect.right - x < 72) box.scrollLeft += 14;
                    });
                }, 25);
            },

            cancelPointerCandidate() {
                if (this.pointerCandidate && this.pointerCandidate.timer) {
                    clearTimeout(this.pointerCandidate.timer);
                }
                this.pointerCandidate = null;
                window.removeEventListener('pointermove', this._boundPointerMove);
                window.removeEventListener('pointerup', this._boundPointerUp);
                window.removeEventListener('pointercancel', this._boundPointerUp);
            },

            endDragCleanup() {
                if (this.autoScrollInterval) {
                    clearInterval(this.autoScrollInterval);
                    this.autoScrollInterval = null;
                }
                this.dragState.active = false;
                this.dragState.sourceId = null;
                this.dragState.overTargetId = null;
                document.body.style.userSelect = '';
                this.clearSwapSelection();
                this.cancelPointerCandidate();
            },

            async executeSwap(itemId1, itemId2, isUndo = false) {
                if (this.isSwapping || !itemId1 || !itemId2 || itemId1 === itemId2) return;
                this.isSwapping = true;
                try {
                    const resp = await fetch(cfg.swapUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': cfg.csrfToken,
                        },
                        body: JSON.stringify({
                            item_id_1: Number(itemId1),
                            item_id_2: Number(itemId2),
                        }),
                    });

                    const data = await resp.json();
                    if (!resp.ok || !data.success) {
                        this.showToast('error', data.message || 'Gagal menukar jadwal.');
                        return;
                    }

                    if (data.items) {
                        Object.keys(data.items).forEach((k) => {
                            const updated = data.items[k];
                            if (updated && this.cellsMap[updated.id]) {
                                this.cellsMap[updated.id] = {
                                    ...this.cellsMap[updated.id],
                                    teacher_code: updated.teacher_code,
                                    teacher_name: updated.teacher_name,
                                    subject_name: updated.subject_name,
                                    user_id: updated.user_id,
                                    subject_id: updated.subject_id,
                                    is_conflict: Boolean(updated.is_conflict),
                                    conflict_notes: updated.conflict_notes,
                                };
                            }
                        });
                    }

                    if (Array.isArray(data.conflicts)) {
                        this.conflictsList = data.conflicts;
                        const conflictNotesById = {};
                        data.conflicts.forEach((c) => {
                            const note = '⚠️ BENTROK: Kode ' + c.teacher_code + ' (' + c.teacher_name + ') di kelas ' + (c.clashing_classes || []).join(' & ');
                            (c.item_ids || []).forEach((cid) => {
                                conflictNotesById[cid] = note;
                            });
                        });
                        Object.values(this.cellsMap).forEach((cell) => {
                            const hasConf = Object.prototype.hasOwnProperty.call(conflictNotesById, cell.id);
                            cell.is_conflict = hasConf;
                            cell.conflict_notes = hasConf ? conflictNotesById[cell.id] : null;
                        });
                    }

                    this.hasUnsyncedSwaps = true;
                    this.clearSwapSelection();

                    if (isUndo) {
                        this.lastSwapUndo = null;
                        this.showToast('success', 'Penukaran terakhir berhasil diurungkan.');
                    } else {
                        this.lastSwapUndo = { itemId1, itemId2 };
                        this.showToast('success', data.message);
                    }
                } catch (err) {
                    console.error('Swap AJAX gagal:', err);
                    this.showToast('error', 'Terjadi kesalahan koneksi saat menukar jadwal.');
                } finally {
                    this.isSwapping = false;
                }
            },

            cellButtonClasses(id, defaultHoverCls) {
                const cell = this.cellsMap[id];
                if (!cell) return '';

                if (this.activeSourceId === id) {
                    return 'bg-indigo-600 text-white font-black ring-2 ring-amber-400 shadow-md scale-[1.04] z-10 cursor-pointer';
                }

                if (this.dragState.active && this.dragState.overTargetId === id) {
                    const clean = Boolean(this.targetCleanMap[id]);
                    return clean
                        ? 'bg-emerald-500 text-white font-black ring-2 ring-emerald-300 shadow-md scale-105 z-10 cursor-copy'
                        : 'bg-amber-500 text-white font-black ring-2 ring-amber-300 shadow-md scale-105 z-10 cursor-copy';
                }

                if (this.activeSourceId) {
                    if (this.isSameClassValidTarget(id)) {
                        const clean = Boolean(this.targetCleanMap[id]);
                        return clean
                            ? 'bg-emerald-100 text-emerald-950 font-black border border-dashed border-emerald-500 hover:bg-emerald-200 cursor-pointer'
                            : 'bg-amber-100 text-amber-950 font-black border border-dashed border-amber-500 hover:bg-amber-200 cursor-pointer';
                    }
                    if (cell.is_conflict) {
                        return 'bg-rose-200/70 text-rose-900 opacity-40 cursor-not-allowed';
                    }
                    return 'opacity-35 text-slate-500 cursor-not-allowed';
                }

                const pulse = (this.pulseCellId === id) ? ' ring-2 ring-indigo-500 shadow-md ' : '';
                const tHighlight = (this.highlightedTeacherCode && !cell.is_activity && this.normCode(cell.teacher_code) === this.highlightedTeacherCode)
                    ? ' ring-2 ring-sky-500 bg-sky-100 text-sky-950 font-black '
                    : '';

                if (cell.is_conflict) {
                    return 'bg-rose-600 text-white font-black ring-1 ring-rose-400 animate-pulse cursor-grab active:cursor-grabbing hover:bg-rose-700' + pulse;
                }

                return (tHighlight || defaultHoverCls) + ' cursor-grab active:cursor-grabbing' + pulse;
            },

            cellTooltip(id) {
                const cell = this.cellsMap[id];
                if (!cell) return '';
                if (this.activeSourceId && this.isSameClassValidTarget(id)) {
                    const stateTxt = this.targetCleanMap[id] ? 'Bebas bentrok bila ditukar ke sel ini' : 'Masih bentrok bila ditukar ke sel ini';
                    return cell.day_name + ' Jam ' + cell.period_order + ' (' + cell.time_range + ') — ' + stateTxt;
                }
                if (cell.is_conflict) {
                    return (cell.conflict_notes || 'BENTROK') + ' | Klik untuk pilih atau tekan & seret ke sel tujuan';
                }
                const info = cell.teacher_code ? ((cell.teacher_name || ('Kode ' + cell.teacher_code)) + (cell.subject_name ? ' (' + cell.subject_name + ')' : '')) : 'Slot Kosong';
                return info + ' | Klik untuk edit, atau tahan & seret untuk tukar slot';
            },

            init() {
                this._boundPointerMove = (e) => this.handleGlobalPointerMove(e);
                this._boundPointerUp = (e) => this.handleGlobalPointerUp(e);
            },

            destroy() {
                this.endDragCleanup();
            },
        };
    };
</script>

<div class="space-y-5"
     x-data="masterScheduleBoard(@js($boardConfig))"
     @keydown.escape.window="clearSwapSelection(); endDragCleanup(); createChoiceModal = false; importScheduleModal = false; editModal = false; swapModal = false;">

    {{-- Ghost Pill Melayang saat Drag & Drop --}}
    <div x-show="dragState.active" x-cloak
         :style="'left:' + (dragState.x + 14) + 'px; top:' + (dragState.y - 26) + 'px;'"
         class="fixed z-[100] pointer-events-none select-none flex items-center gap-2 px-3 py-2 rounded-2xl shadow-2xl border-2 text-xs font-extrabold transition-colors"
         :class="dragState.overTargetId ? (targetCleanMap[dragState.overTargetId] ? 'bg-emerald-600 text-white border-emerald-300' : 'bg-amber-500 text-white border-amber-200') : 'bg-indigo-700 text-white border-amber-300'">
        <span class="px-2 py-0.5 rounded-lg bg-white/20 font-black"
              x-text="activeSourceCell ? ('Kelas ' + activeSourceCell.class_name) : ''"></span>
        <span x-text="activeSourceCell ? (activeSourceCell.teacher_code ? ('Kode ' + activeSourceCell.teacher_code) : 'Kosong') : ''"></span>
        <span class="text-[10px] font-bold opacity-90"
              x-text="dragState.overTargetId ? (targetCleanMap[dragState.overTargetId] ? '→ Lepas (Bebas Bentrok ✓)' : '→ Lepas (Masih Bentrok !)') : (activeSourceCell ? ('Geser ke kolom ' + activeSourceCell.class_name) : '')"></span>
    </div>

    {{-- Header Action Bar --}}
    <div class="bg-white rounded-2xl border border-slate-200 p-4 md:p-5 shadow-2xs flex flex-wrap items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 flex-wrap">
                <span class="px-2.5 py-1 rounded-lg bg-emerald-100 text-emerald-800 text-xs font-bold">
                    Tahun Ajaran: {{ $academicYear->full_label ?? '-' }}
                </span>
                <span class="px-2.5 py-1 rounded-lg bg-slate-100 text-slate-700 text-xs font-bold">
                    {{ $matrixData['total_classes'] ?? 21 }} Rombel &bull; {{ $isTwoShift ? '2 Shift (Pagi & Siang)' : '1 Shift (Semua Rombel)' }}
                </span>
                <span x-show="hasUnsyncedSwaps" x-cloak
                      class="px-2.5 py-1 rounded-lg bg-amber-100 text-amber-900 text-xs font-extrabold animate-pulse">
                    Jadwal Diubah — Jangan lupa klik Sinkronkan ke Guru
                </span>
            </div>
            <h2 class="text-base font-extrabold text-slate-900 mt-2">Pusat Pengaturan Jadwal Pelajaran Wakasek</h2>
        </div>

        <div class="flex flex-wrap items-center gap-2.5 w-full sm:w-auto">
            {{-- Tombol Utama: Buat Jadwal Baru dari Awal (Pop-up Pilihan: Import Excel vs 6 Tahapan Wizard) --}}
            <button type="button"
                    @click="createChoiceStep = 'choose'; createChoiceModal = true"
                    class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs shadow-xs transition cursor-pointer">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M12 4v16m8-8H4"/></svg>
                <span>Buat Jadwal Baru dari Awal</span>
            </button>

            {{-- Tombol Sinkronkan ke Kelas Guru --}}
            <form method="POST" action="{{ route('admin.schedules.master.sync-classes') }}"
                  onsubmit="return confirm('Sinkronkan seluruh jadwal dari Master Jadwal ke Buku Administrasi Kelas Guru? Seluruh hari mengajar & jam pelajaran guru akan diperbarui otomatis.')">
                @csrf
                <button type="submit"
                        class="inline-flex items-center gap-1.5 px-3.5 py-2.5 rounded-xl font-bold text-xs transition border"
                        :class="hasUnsyncedSwaps ? 'bg-indigo-600 hover:bg-indigo-700 text-white border-indigo-600 shadow-sm' : 'bg-indigo-50 hover:bg-indigo-100 text-indigo-700 border-indigo-200'">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    <span>Sinkronkan ke Guru</span>
                </button>
            </form>

            {{-- Cetak / Ekspor --}}
            <a href="{{ route('admin.schedules.master.export', ['academic_year_id' => $academicYear?->id]) }}" target="_blank"
               class="inline-flex items-center gap-1.5 px-3.5 py-2.5 rounded-xl bg-white hover:bg-slate-50 text-slate-700 font-bold text-xs transition border border-slate-300">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                <span>Cetak / Cetak PDF</span>
            </a>

            {{-- Export Excel Poster --}}
            <a href="{{ route('admin.schedules.master.export-excel', ['academic_year_id' => $academicYear?->id]) }}"
               class="inline-flex items-center gap-1.5 px-3.5 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs transition shadow-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                <span>Export Excel</span>
            </a>

            {{-- Import Excel / CSV --}}
            <button type="button" @click="importScheduleModal = true"
               class="inline-flex items-center gap-1.5 px-3.5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs transition shadow-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                <span>Import Excel</span>
            </button>
        </div>
    </div>

    {{-- Banner Status Kesiapan 2 Pilar Master Data (Master Guru & Master Rombel) --}}
    @if(isset($prerequisites))
        @if(!$prerequisites['is_ready'])
            <div class="rounded-2xl bg-amber-50 border border-amber-300 p-4 md:p-5 shadow-2xs space-y-3" x-data="{ detailOpen: false }">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex items-start gap-3 min-w-0">
                        <div class="w-9 h-9 rounded-xl bg-amber-500 text-white flex items-center justify-center shrink-0 font-bold text-base shadow-xs">
                            ⚠️
                        </div>
                        <div class="min-w-0">
                            <h3 class="text-sm font-extrabold text-amber-950 flex items-center gap-2 flex-wrap">
                                <span>Perhatian: Data Master Prasyarat Belum Lengkap untuk Pembuatan Jadwal Baru</span>
                            </h3>
                            <p class="text-xs text-amber-800 mt-0.5 leading-relaxed">
                                Master Jadwal Sekolah membutuhkan <strong>Data Master Guru</strong> dan <strong>Master Rombel &amp; Murid</strong> yang lengkap sebelum jadwal baru dapat disusun dari awal.
                            </p>
                        </div>
                    </div>
                    <button type="button" @click="detailOpen = !detailOpen"
                            class="px-3 py-1.5 rounded-lg bg-amber-200/80 hover:bg-amber-300 text-amber-950 font-bold text-xs transition shrink-0 cursor-pointer">
                        <span x-text="detailOpen ? 'Tutup Checklist' : 'Lihat Checklist Kesiapan'"></span>
                    </button>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 pt-1">
                    {{-- Status Pilar 1: Master Guru --}}
                    <div class="p-3.5 rounded-xl bg-white border {{ $prerequisites['teachers']['is_ready'] ? 'border-emerald-200 bg-emerald-50/30' : 'border-amber-300 bg-amber-50/50' }} space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-slate-800 flex items-center gap-1.5">
                                @if($prerequisites['teachers']['is_ready'])
                                    <span class="text-emerald-600">✅</span>
                                @else
                                    <span class="text-amber-600">⚠️</span>
                                @endif
                                <span>1. Data Master Guru</span>
                            </span>
                            <span class="text-[11px] font-bold px-2 py-0.5 rounded {{ $prerequisites['teachers']['is_ready'] ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-900' }}">
                                {{ $prerequisites['teachers']['complete'] }}/{{ $prerequisites['teachers']['total'] }} Guru Siap
                            </span>
                        </div>
                        <p class="text-[11px] text-slate-600">
                            @if($prerequisites['teachers']['is_ready'])
                                Seluruh guru telah memiliki mata pelajaran dan kode mengajar di Master Guru.
                            @elseif($prerequisites['teachers']['total'] === 0)
                                Belum ada data guru aktif di Master Guru.
                            @else
                                Terdapat <strong>{{ $prerequisites['teachers']['incomplete_count'] }}</strong> guru yang belum memiliki kode mengajar / mapel.
                            @endif
                        </p>
                        <div class="pt-1">
                            <a href="{{ route('admin.teachers.index') }}"
                               class="inline-flex items-center gap-1 text-xs font-bold text-emerald-700 hover:text-emerald-800 hover:underline">
                                <span>Buka Menu Master Guru &rarr;</span>
                            </a>
                        </div>
                    </div>

                    {{-- Status Pilar 2: Master Rombel & Murid --}}
                    <div class="p-3.5 rounded-xl bg-white border {{ $prerequisites['classes']['is_ready'] ? 'border-emerald-200 bg-emerald-50/30' : 'border-amber-300 bg-amber-50/50' }} space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-slate-800 flex items-center gap-1.5">
                                @if($prerequisites['classes']['is_ready'])
                                    <span class="text-emerald-600">✅</span>
                                @else
                                    <span class="text-amber-600">⚠️</span>
                                @endif
                                <span>2. Master Rombel &amp; Murid</span>
                            </span>
                            <span class="text-[11px] font-bold px-2 py-0.5 rounded {{ $prerequisites['classes']['is_ready'] ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-900' }}">
                                {{ $prerequisites['classes']['total'] }} Rombel Terkunci
                            </span>
                        </div>
                        <p class="text-[11px] text-slate-600">
                            @if($prerequisites['classes']['is_ready'])
                                Daftar rombel kelas terkunci dari database (Tingkat: {{ collect($prerequisites['classes']['by_grade'])->map(fn($v, $k) => "$k: $v rombel")->join(', ') }}).
                            @else
                                Belum ada rombel kelas yang terdaftar pada tahun ajaran ini di Master Rombel.
                            @endif
                        </p>
                        <div class="pt-1">
                            <a href="{{ route('admin.classes.index') }}"
                               class="inline-flex items-center gap-1 text-xs font-bold text-indigo-700 hover:text-indigo-800 hover:underline">
                                <span>Buka Master Rombel &amp; Murid &rarr;</span>
                            </a>
                        </div>
                    </div>
                </div>

                {{-- Detail Guru yang Belum Lengkap (Expandable) --}}
                @if(!$prerequisites['teachers']['is_ready'] && !empty($prerequisites['teachers']['incomplete_list']))
                    <div x-show="detailOpen" x-cloak class="mt-2 pt-3 border-t border-amber-200/80 space-y-2">
                        <h4 class="text-xs font-extrabold text-amber-950">Daftar Guru yang Perlu Dilengkapi di Master Guru:</h4>
                        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-2">
                            @foreach($prerequisites['teachers']['incomplete_list'] as $inc)
                                <div class="p-2 rounded-lg bg-white border border-amber-200 text-xs">
                                    <div class="font-bold text-slate-900 truncate">{{ $inc['name'] }}</div>
                                    <div class="text-[11px] text-rose-600 font-semibold">{{ $inc['missing'] }}</div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        @else
            <div class="p-3 rounded-2xl bg-emerald-50 border border-emerald-200 text-xs text-emerald-900 flex items-center justify-between flex-wrap gap-2">
                <div class="flex items-center gap-2">
                    <span class="text-sm">✅</span>
                    <span class="font-bold">Prasyarat Master Data Siap &amp; Terkunci:</span>
                    <span>{{ $prerequisites['teachers']['complete'] }} Guru Master Lengkap &bull; {{ $prerequisites['classes']['total'] }} Rombel Kelas Terkunci dari Master Rombel</span>
                </div>
                <div class="flex items-center gap-2">
                    <a href="{{ route('admin.teachers.index') }}" class="font-semibold text-emerald-700 hover:underline text-[11px]">Master Guru</a>
                    <span>&bull;</span>
                    <a href="{{ route('admin.classes.index') }}" class="font-semibold text-emerald-700 hover:underline text-[11px]">Master Rombel</a>
                </div>
            </div>
        @endif
    @endif

    {{-- Banner Peringatan Jadwal Bertabrakan (Live Reactive via Alpine conflictsList) --}}
    <div x-show="conflictsList.length > 0" {{ count($conflicts) === 0 ? 'x-cloak' : '' }}
         class="rounded-2xl bg-rose-50 border border-rose-200 p-4 md:p-5 shadow-2xs">
        <div class="flex items-start justify-between gap-3 flex-wrap">
            <div class="flex items-start gap-3">
                <div class="w-9 h-9 rounded-xl bg-rose-600 text-white flex items-center justify-center shrink-0 font-bold text-base shadow-xs">
                    ⚠️
                </div>
                <div>
                    <h3 class="text-sm font-extrabold text-rose-900">
                        <span x-text="'Terdeteksi ' + conflictsList.length + ' Titik Jadwal Bertabrakan (Bentrok Jam Sama)'">
                            Terdeteksi {{ count($conflicts) }} Titik Jadwal Bertabrakan (Bentrok Jam Sama)
                        </span>
                    </h3>
                    <p class="text-xs text-rose-700 mt-0.5 leading-relaxed">
                        Langsung <strong>klik sel berwarna merah (bentrok)</strong> lalu tap sel tujuan di kelas yang sama (<strong>Select &amp; Tap</strong>), atau <strong>tekan &amp; tahan lalu geser</strong> ke sel tujuan (<strong>Drag &amp; Drop</strong>) pada seluruh hari Senin–Sabtu.
                    </p>
                </div>
            </div>
            <button type="button" @click="conflictsExpanded = !conflictsExpanded"
                    class="px-3 py-1.5 rounded-lg bg-rose-200/80 hover:bg-rose-300/80 text-rose-900 font-bold text-xs transition shrink-0 cursor-pointer">
                <span x-text="conflictsExpanded ? 'Tutup Rincian' : 'Lihat ' + conflictsList.length + ' Bentrok'"></span>
            </button>
        </div>

        <div x-show="conflictsExpanded" x-collapse x-cloak class="mt-4 pt-3 border-t border-rose-200/70 space-y-2">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-2.5">
                <template x-for="(c, idx) in conflictsList" :key="'conf_' + idx + '_' + c.day_of_week + '_' + c.teacher_code">
                    <div class="p-3 rounded-xl bg-white border border-rose-200 shadow-2xs flex items-center justify-between gap-3 flex-wrap">
                        <div class="min-w-0">
                            <div class="flex items-center gap-1.5 text-xs font-extrabold text-rose-900">
                                <span class="px-1.5 py-0.5 rounded bg-rose-100 text-rose-800 text-[10px]" x-text="'#' + (idx + 1)"></span>
                                <span x-text="c.day_name + ' • Shift ' + c.shift"></span>
                                <span class="text-rose-600 font-semibold" x-text="'(' + c.time_range + ')'"></span>
                            </div>
                            <div class="text-xs text-slate-800 font-bold mt-1 truncate"
                                 x-text="'Kode ' + c.teacher_code + ': ' + c.teacher_name"></div>
                            <div class="text-[11px] text-slate-500">
                                <span x-text="'Mapel: ' + c.subject_name + ' • Kelas: '"></span>
                                <span class="font-extrabold text-rose-700 underline" x-text="(c.clashing_classes || []).join(' & ')"></span>
                            </div>
                        </div>
                        <div class="flex items-center gap-1.5 shrink-0 flex-wrap">
                            <template x-for="(clsName, cIdx) in (c.clashing_classes || [])" :key="cIdx">
                                <button type="button"
                                        x-show="c.item_ids && c.item_ids[cIdx]"
                                        @click="focusConflictCell(c.item_ids[cIdx], true)"
                                        class="px-2.5 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-[11px] font-bold transition cursor-pointer"
                                        x-text="'Pilih ' + clsName + ' →'"></button>
                            </template>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>

    <div x-show="conflictsList.length === 0" {{ count($conflicts) > 0 ? 'x-cloak' : '' }}
         class="rounded-2xl bg-emerald-50 border border-emerald-200 p-4 flex items-center justify-between gap-3 shadow-2xs">
        <div class="flex items-center gap-3">
            <div class="w-8 h-8 rounded-xl bg-emerald-600 text-white flex items-center justify-center font-extrabold text-sm shrink-0">
                ✓
            </div>
            <div>
                <h3 class="text-sm font-extrabold text-emerald-950">Jadwal Sempurna &amp; Bebas Bentrok</h3>
                <p class="text-xs text-emerald-700">Tidak ada jadwal guru yang bertabrakan pada hari dan jam yang sama.</p>
            </div>
        </div>
        <span class="px-2.5 py-1 rounded-md bg-emerald-200 text-emerald-900 font-bold text-xs">Valid</span>
    </div>

    {{-- Toast Feedback + Undo Penukaran Terakhir --}}
    <div x-show="toast || lastSwapUndo" x-cloak
         class="rounded-2xl border p-3.5 shadow-sm flex flex-wrap items-center justify-between gap-3 transition"
         :class="toast?.type === 'error' ? 'bg-rose-50 border-rose-300 text-rose-900' : (toast?.type === 'warning' ? 'bg-amber-50 border-amber-300 text-amber-900' : 'bg-emerald-50 border-emerald-300 text-emerald-900')">
        <div class="flex items-center gap-2.5 text-xs font-bold min-w-0">
            <span x-text="toast ? toast.message : 'Penukaran terakhir selesai.'"></span>
        </div>
        <div class="flex items-center gap-2 shrink-0">
            <button type="button" x-show="lastSwapUndo"
                    :disabled="isSwapping"
                    @click="executeSwap(lastSwapUndo.itemId1, lastSwapUndo.itemId2, true)"
                    class="px-3 py-1.5 rounded-xl bg-white hover:bg-slate-100 border border-slate-300 text-slate-800 text-xs font-extrabold transition cursor-pointer shadow-2xs">
                ⇄ Urungkan Penukaran Terakhir
            </button>
            <button type="button" @click="toast = null; lastSwapUndo = null" class="text-slate-500 hover:text-slate-900 font-bold px-1.5 cursor-pointer">&times;</button>
        </div>
    </div>

    {{-- Sticky Bar saat Sel Dipilih (Select & Tap) --}}
    <div x-show="activeSourceCell" x-cloak
         class="sticky top-16 z-30 rounded-2xl bg-indigo-950 text-white border-2 border-amber-400 p-3.5 md:p-4 shadow-xl flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-3 min-w-0">
            <span class="px-2.5 py-1 rounded-xl bg-amber-400 text-slate-950 font-black text-xs shrink-0"
                  x-text="activeSourceCell ? ('DIPILIH: KELAS ' + activeSourceCell.class_name) : ''"></span>
            <div class="min-w-0">
                <div class="text-xs font-extrabold truncate"
                     x-text="activeSourceCell ? (activeSourceCell.day_name + ' • Jam ' + activeSourceCell.period_order + ' (' + activeSourceCell.time_range + ') — ' + (activeSourceCell.teacher_code ? ('Kode ' + activeSourceCell.teacher_code + ' (' + (activeSourceCell.teacher_name || '-') + ')') : 'Slot Kosong')) : ''"></div>
                <div class="text-[11px] text-indigo-200 mt-0.5">
                    Arahkan &amp; tap sel berwarna <span class="text-emerald-300 font-bold">hijau (Bebas Bentrok: <span x-text="cleanTargetsCount"></span> sel)</span> atau <span class="text-amber-300 font-bold">kuning</span> di kolom Kelas <strong x-text="activeSourceCell?.class_name"></strong>.
                </div>
            </div>
        </div>
        <div class="flex items-center gap-2 shrink-0">
            <button type="button"
                    @click="let c = activeSourceCell; clearSwapSelection(); openEdit(c)"
                    class="px-3 py-1.5 rounded-xl bg-white/15 hover:bg-white/25 text-white text-xs font-bold transition cursor-pointer">
                Edit Guru Sel Ini
            </button>
            <button type="button"
                    @click="clearSwapSelection()"
                    class="px-3 py-1.5 rounded-xl bg-rose-500 hover:bg-rose-600 text-white text-xs font-extrabold transition cursor-pointer">
                ✕ Batal Pilih (Esc)
            </button>
        </div>
    </div>

    {{-- Bar Filter Semua Hari + Mode Tukar Cepat + Indikator Sorot Kode Guru --}}
    <div class="bg-white rounded-2xl border border-slate-200 p-3 shadow-2xs space-y-2.5">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div class="flex items-center gap-1.5 overflow-x-auto no-scrollbar flex-1 min-w-0">
                <button type="button"
                        @click="setDayFilter('all')"
                        class="px-3.5 py-2 rounded-xl font-extrabold text-xs transition shrink-0 cursor-pointer flex items-center gap-1.5"
                        :class="activeDayFilter === 'all' ? 'bg-emerald-600 text-white shadow-xs' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'">
                    <span>SEMUA HARI (SENIN–SABTU)</span>
                </button>
                @foreach($daysMap as $dNum => $dName)
                    <button type="button"
                            @click="setDayFilter(activeDayFilter === '{{ $dNum }}' ? 'all' : '{{ $dNum }}')"
                            class="px-3 py-2 rounded-xl font-bold text-xs transition shrink-0 cursor-pointer flex items-center gap-1.5"
                            :class="activeDayFilter === '{{ $dNum }}' ? 'bg-emerald-600 text-white shadow-xs' : 'text-slate-600 hover:bg-slate-100'">
                        <span>{{ strtoupper($dName) }}</span>
                        <span x-show="dayConflictsCount({{ $dNum }}) > 0" x-cloak
                              class="px-1.5 py-0.2 rounded-full text-[10px] font-extrabold"
                              :class="activeDayFilter === '{{ $dNum }}' ? 'bg-white text-rose-700' : 'bg-rose-100 text-rose-700'"
                              x-text="dayConflictsCount({{ $dNum }}) + ' ⚠️'"></span>
                    </button>
                @endforeach
            </div>

            <div class="flex items-center gap-2 shrink-0 flex-wrap">
                <button type="button" x-show="highlightedTeacherCode !== ''" x-cloak
                        @click="highlightedTeacherCode = ''"
                        class="px-3 py-1.5 rounded-xl bg-sky-100 hover:bg-sky-200 text-sky-900 text-xs font-extrabold transition cursor-pointer">
                    Sorot Kode <span x-text="highlightedTeacherCode"></span> &times;
                </button>
                <button type="button"
                        @click="quickSwapMode = !quickSwapMode; clearSwapSelection()"
                        class="px-3 py-1.5 rounded-xl border text-xs font-bold transition cursor-pointer flex items-center gap-1.5"
                        :class="quickSwapMode ? 'bg-indigo-600 text-white border-indigo-600 shadow-xs' : 'bg-slate-50 text-slate-700 border-slate-300 hover:bg-slate-100'"
                        title="Jika aktif, mengklik sel normal juga langsung memilih sel untuk ditukar (Select & Tap)">
                    <span>⇄ Mode Tukar Cepat Semua Sel</span>
                </button>
            </div>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-2 text-[11px] text-slate-500 pt-1 border-t border-slate-100">
            <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                <span>
                    <strong class="text-slate-700">Cara Cepat:</strong>
                    (1) <strong>Select &amp; Tap:</strong> klik kotak merah &rarr; klik tujuan sekelas, atau
                    (2) <strong>Drag &amp; Drop:</strong> tekan &amp; tahan lalu geser &amp; lepaskan.
                </span>
                <span class="inline-flex items-center gap-2 text-[10px] font-bold text-slate-600">
                    <span class="inline-flex items-center gap-1"><span class="w-2.5 h-2.5 rounded bg-rose-600 inline-block"></span>Bentrok</span>
                    <span class="inline-flex items-center gap-1"><span class="w-2.5 h-2.5 rounded bg-indigo-600 ring-1 ring-amber-400 inline-block"></span>Terpilih</span>
                    <span class="inline-flex items-center gap-1"><span class="w-2.5 h-2.5 rounded bg-emerald-100 border border-emerald-500 inline-block"></span>Tujuan Aman</span>
                    <span class="inline-flex items-center gap-1"><span class="w-2.5 h-2.5 rounded bg-amber-100 border border-amber-500 inline-block"></span>Tujuan Bentrok</span>
                </span>
            </div>
            <span class="font-semibold text-slate-600" x-show="isSwapping" x-cloak>Menyimpan penukaran...</span>
        </div>
    </div>

    {{-- MATRIKS JADWAL SEMUA HARI (DINAMIS 1 SHIFT / 2 SHIFT, STICKY HEADER & KOLOM KIRI) --}}
    @php
        $gradeHeaderThemes = [
            '7' => ['th' => 'bg-emerald-50 text-emerald-900', 'btn' => 'hover:bg-emerald-50 hover:text-emerald-900'],
            '8' => ['th' => 'bg-amber-50 text-amber-900',     'btn' => 'hover:bg-amber-50 hover:text-amber-900'],
            '9' => ['th' => 'bg-blue-50 text-blue-900',       'btn' => 'hover:bg-blue-50 hover:text-blue-900'],
        ];

        $hasAnySiangSlots = collect($matrixByDay)->contains(fn ($d) => count($d['siang_slots'] ?? []) > 0);
        $mondayRef = $matrixByDay[1] ?? collect($matrixByDay)->first();

        $shiftSections = [
            [
                'key' => 'pagi',
                'title' => $shift1Meta['title'] ?? 'Jadwal 1 Shift: Semua Rombel',
                'time_range' => $mondayRef['pagi_time_range'] ?? '-',
                'banner_bg' => 'from-emerald-950 via-slate-900 to-slate-900',
                'dot_bg' => 'bg-emerald-400',
                'classes' => $shift1Meta['flat_classes'] ?? ['7A','7B','7C','7D','7E','7F','7G','8A','8B','8C','8D','8E','8F','8G','9A','9B','9C','9D','9E','9F','9G'],
                'slot_key' => 'pagi_slots',
                'empty_text' => 'Belum ada data jadwal. Klik "Buat Jadwal Baru dari Awal" atau "Import Excel".',
            ],
        ];

        if ($isTwoShift || $hasAnySiangSlots) {
            $shiftSections[] = [
                'key' => 'siang',
                'title' => $shift2Meta['title'] ?? 'Shift Siang: Kelas VIII (8A–8G)',
                'time_range' => $mondayRef['siang_time_range'] ?? '-',
                'banner_bg' => 'from-amber-950 via-slate-900 to-slate-900',
                'dot_bg' => 'bg-amber-400',
                'classes' => $shift2Meta['flat_classes'] ?? ['8A','8B','8C','8D','8E','8F','8G'],
                'slot_key' => 'siang_slots',
                'empty_text' => 'Belum ada data jadwal untuk Shift Siang.',
            ];
        }
    @endphp

    <div class="space-y-6">
        @foreach($shiftSections as $sec)
            @php
                $secClasses = $sec['classes'];
                $secColCount = max(1, count($secClasses));
                $secSlotKey = $sec['slot_key'];
                $hasAnyRowInSection = collect($matrixByDay)->contains(fn ($d) => count($d[$secSlotKey] ?? []) > 0);
            @endphp
            <div class="bg-white rounded-2xl border border-slate-200 shadow-2xs overflow-visible">
                <div class="p-4 rounded-t-2xl bg-gradient-to-r {{ $sec['banner_bg'] }} text-white flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <span class="w-3 h-3 rounded-full {{ $sec['dot_bg'] }}"></span>
                        <h3 class="text-sm font-extrabold uppercase tracking-wide">
                            {{ $sec['title'] }}
                        </h3>
                    </div>
                    <span class="text-xs text-slate-300 font-medium">Senin s.d. Sabtu &bull; Jam Belajar: {{ $sec['time_range'] }}</span>
                </div>

                <div data-matrix-scroll class="overflow-x-auto lg:overflow-x-visible no-scrollbar rounded-b-2xl relative">
                    <table class="w-full text-xs text-center border-collapse">
                        <thead class="sticky top-0 lg:top-[60px] z-20 shadow-2xs">
                            <tr class="bg-slate-100 border-b-2 border-slate-300 text-slate-700 font-extrabold">
                                <th class="p-1 border-r border-slate-200 bg-slate-100 text-[10px] w-[54px] min-w-[54px] sticky left-0 z-30">Hari</th>
                                <th class="p-1 border-r border-slate-200 bg-slate-100 text-[10px] w-[32px] min-w-[32px] sticky left-[54px] z-30">Jam</th>
                                <th class="px-1 py-1 border-r border-slate-200 bg-slate-100 text-[10px] w-[78px] min-w-[78px] whitespace-nowrap sticky left-[86px] z-30">Waktu</th>
                                @foreach($secClasses as $c)
                                    @php
                                        $gKey = preg_match('/^(\d+)/', $c, $gm) ? $gm[1] : '';
                                        $gTh = $gradeHeaderThemes[$gKey]['th'] ?? 'bg-slate-50 text-slate-900';
                                    @endphp
                                    <th class="px-0.5 py-1.5 border-r border-slate-200 {{ $gTh }} text-[11px] min-w-[34px]"
                                        :class="activeSourceCell && activeSourceCell.class_name === '{{ $c }}' ? 'ring-2 ring-inset ring-indigo-500 font-black' : ''">
                                        {{ $c }}
                                    </th>
                                @endforeach
                            </tr>
                        </thead>

                        @if($hasAnyRowInSection)
                            @foreach($daysMap as $dNum => $dName)
                                @php
                                    $daySlots = $matrixByDay[$dNum][$secSlotKey] ?? collect();
                                    $daySlotCount = count($daySlots);
                                    $dayLabelUpper = $dNum === 5 ? "JUM'AT" : strtoupper($dName);
                                @endphp
                                @if($daySlotCount > 0)
                                    <tbody x-show="activeDayFilter === 'all' || activeDayFilter === '{{ $dNum }}'"
                                           data-day-section="{{ $dNum }}"
                                           class="divide-y divide-slate-100 font-medium text-slate-800 border-t-2 border-slate-300">
                                        @foreach($daySlots as $slotIdx => $slot)
                                            @if($slot['is_activity'])
                                                <tr class="bg-amber-50/80 font-bold text-amber-900">
                                                    @if($slotIdx === 0)
                                                        <td rowspan="{{ $daySlotCount }}"
                                                            class="p-1 border-r border-slate-200 bg-slate-50 font-black text-[10px] text-slate-800 align-middle sticky left-0 z-10 w-[54px] min-w-[54px]">
                                                            {{ $dayLabelUpper }}
                                                        </td>
                                                    @endif
                                                    <td class="p-1 border-r border-slate-200 bg-amber-50 text-slate-500 font-semibold text-[10px] sticky left-[54px] z-10 w-[32px] min-w-[32px]">
                                                        {{ $slot['period_order'] ?: '-' }}
                                                    </td>
                                                    <td class="px-1 py-0.5 border-r border-slate-200 bg-amber-50 whitespace-nowrap text-[10px] sticky left-[86px] z-10 w-[78px] min-w-[78px]">
                                                        {{ $slot['time_range'] }}
                                                    </td>
                                                    <td colspan="{{ $secColCount }}" class="p-1 text-center text-[11px] uppercase tracking-wider text-amber-800">
                                                        {{ $slot['activity_name'] }}
                                                    </td>
                                                </tr>
                                            @else
                                                <tr class="hover:bg-slate-50/50 transition">
                                                    @if($slotIdx === 0)
                                                        <td rowspan="{{ $daySlotCount }}"
                                                            class="p-1 border-r border-slate-200 bg-slate-50 font-black text-[10px] text-slate-800 align-middle sticky left-0 z-10 w-[54px] min-w-[54px]">
                                                            {{ $dayLabelUpper }}
                                                        </td>
                                                    @endif
                                                    <td class="p-1 border-r border-slate-200 font-bold text-slate-600 bg-slate-50 text-[10px] sticky left-[54px] z-10 w-[32px] min-w-[32px]">
                                                        {{ $slot['period_order'] }}
                                                    </td>
                                                    <td class="px-1 py-0.5 border-r border-slate-200 font-semibold text-slate-600 bg-white whitespace-nowrap text-[10px] sticky left-[86px] z-10 w-[78px] min-w-[78px]">
                                                        {{ $slot['time_range'] }}
                                                    </td>

                                                    @foreach($secClasses as $c)
                                                        @php
                                                            $item = $slot['classes'][$c] ?? null;
                                                            $gKey = preg_match('/^(\d+)/', $c, $gm) ? $gm[1] : '';
                                                            $gBtn = $gradeHeaderThemes[$gKey]['btn'] ?? 'hover:bg-emerald-50 hover:text-emerald-900';
                                                            $cid = $item?->id ? (int) $item->id : null;
                                                        @endphp
                                                        <td class="p-0.5 border-r border-slate-200 transition-colors"
                                                            @if($cid)
                                                                data-schedule-cell-id="{{ $cid }}"
                                                                :class="cellsMap[{{ $cid }}]?.is_conflict && activeSourceId !== {{ $cid }} ? 'bg-rose-50' : ''"
                                                            @endif>
                                                            @if($cid)
                                                                <button type="button"
                                                                        id="schedule-cell-{{ $cid }}"
                                                                        data-schedule-cell-id="{{ $cid }}"
                                                                        @click="onCellClick({{ $cid }})"
                                                                        @pointerdown="onCellPointerDown($event, {{ $cid }})"
                                                                        :title="cellTooltip({{ $cid }})"
                                                                        :aria-label="cellTooltip({{ $cid }})"
                                                                        :data-conflict="cellsMap[{{ $cid }}]?.is_conflict ? '1' : '0'"
                                                                        data-conflict="{{ $item->is_conflict ? '1' : '0' }}"
                                                                        style="touch-action: pan-x pan-y;"
                                                                        class="w-full h-6 min-h-[24px] px-0.5 rounded text-center transition select-none flex items-center justify-center relative {{ $item->is_conflict ? 'bg-rose-600 text-white' : '' }}"
                                                                        :class="cellButtonClasses({{ $cid }}, '{{ $gBtn }}')">
                                                                    <span class="text-[11px] font-black leading-none"
                                                                          x-text="cellsMap[{{ $cid }}]?.teacher_code || '-'">{{ $item->teacher_code ?: '-' }}</span>
                                                                </button>
                                                            @else
                                                                <span class="text-slate-300 text-[11px]">-</span>
                                                            @endif
                                                        </td>
                                                    @endforeach
                                                </tr>
                                            @endif
                                        @endforeach
                                    </tbody>
                                @endif
                            @endforeach
                        @else
                            <tbody>
                                <tr>
                                    <td colspan="{{ 3 + $secColCount }}" class="p-8 text-center text-slate-400">
                                        {{ $sec['empty_text'] }}
                                    </td>
                                </tr>
                            </tbody>
                        @endif
                    </table>
                </div>
            </div>
        @endforeach
    </div>

    {{-- MODAL GERBANG PILIHAN: BUAT JADWAL BARU DARI AWAL (IMPORT EXCEL VS 6 TAHAPAN WIZARD) --}}
    <div x-show="createChoiceModal" x-cloak
         @keydown.escape.window="createChoiceModal = false"
         class="fixed inset-0 z-50 overflow-y-auto"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100">
        <div class="fixed inset-0 bg-slate-950/70 backdrop-blur-xs" @click="createChoiceModal = false"></div>
        <div class="min-h-full flex items-center justify-center p-4">
            <div class="relative bg-white rounded-3xl shadow-2xl max-w-2xl w-full p-6 sm:p-7 overflow-hidden border border-slate-100"
                 @click.stop>
                <div class="flex items-start justify-between pb-4 border-b border-slate-100 mb-5">
                    <div>
                        <div class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-emerald-100 text-emerald-800 text-[11px] font-extrabold mb-1.5">
                            Langkah Awal Penyusunan Jadwal
                        </div>
                        <h3 class="text-lg font-extrabold text-slate-900"
                            x-text="createChoiceStep === 'import' ? 'Buat Jadwal Baru dengan Import File Excel' : 'Buat Jadwal Baru dari Awal'"></h3>
                        <p class="text-xs text-slate-500"
                           x-text="createChoiceStep === 'import' ? 'Unduh template 1/2 Shift lalu unggah file Excel jadwal untuk langsung diterapkan.' : 'Pilih cara pembuatan jadwal baru untuk semester {{ $academicYear?->full_label ?? '-' }} sebelum masuk ke 6 tahapan wizard.'"></p>
                    </div>
                    <button type="button" @click="createChoiceModal = false" class="text-slate-400 hover:text-slate-600 p-1 cursor-pointer">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                {{-- Pilihan Metode Awal --}}
                <div x-show="createChoiceStep === 'choose'" class="space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        {{-- Pilihan 1: Buat via Import File Excel --}}
                        <button type="button"
                                @click="createChoiceStep = 'import'"
                                class="text-left p-5 rounded-2xl border-2 border-indigo-200 hover:border-indigo-500 bg-indigo-50/40 hover:bg-indigo-50 transition group cursor-pointer flex flex-col justify-between gap-4">
                            <div class="space-y-2.5">
                                <div class="flex items-center justify-between">
                                    <span class="w-10 h-10 rounded-xl bg-indigo-600 text-white flex items-center justify-center shadow-xs">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                                    </span>
                                    <span class="px-2 py-0.5 rounded-full bg-indigo-100 text-indigo-800 text-[10px] font-extrabold uppercase">Cepat dari Excel</span>
                                </div>
                                <h4 class="text-sm font-extrabold text-slate-900">Import File Excel (.xlsx / .csv)</h4>
                                <p class="text-xs text-slate-600 leading-relaxed">
                                    Sudah punya susunan jadwal atau ingin mengisi lewat Excel? Pilih template <strong>1 Shift / 2 Shift</strong> lalu unggah file untuk langsung membuat jadwal baru.
                                </p>
                            </div>
                            <span class="inline-flex items-center gap-1 text-xs font-extrabold text-indigo-700 group-hover:translate-x-0.5 transition">
                                <span>Buat dengan Import Excel</span>
                                <span>&rarr;</span>
                            </span>
                        </button>

                        {{-- Pilihan 2: Lanjut ke 6 Tahapan Wizard --}}
                        <a href="{{ route('admin.schedules.master.builder') }}"
                           class="text-left p-5 rounded-2xl border-2 border-emerald-200 hover:border-emerald-500 bg-emerald-50/40 hover:bg-emerald-50 transition group flex flex-col justify-between gap-4">
                            <div class="space-y-2.5">
                                <div class="flex items-center justify-between">
                                    <span class="w-10 h-10 rounded-xl bg-emerald-600 text-white flex items-center justify-center shadow-xs">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                                    </span>
                                    <span class="px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800 text-[10px] font-extrabold uppercase">6 Tahapan Wizard</span>
                                </div>
                                <h4 class="text-sm font-extrabold text-slate-900">Susun Lewat 6 Tahapan Wizard</h4>
                                <p class="text-xs text-slate-600 leading-relaxed">
                                    Atur model shift (1/2 shift), durasi JP, kegiatan non-KBM, beban mengajar guru, dan generate jadwal otomatis anti-bentrok (*Smart Timetable Builder*).
                                </p>
                            </div>
                            <span class="inline-flex items-center gap-1 text-xs font-extrabold text-emerald-700 group-hover:translate-x-0.5 transition">
                                <span>Lanjut ke 6 Tahapan</span>
                                <span>&rarr;</span>
                            </span>
                        </a>
                    </div>

                    <div class="pt-3 flex justify-end border-t border-slate-100">
                        <button type="button" @click="createChoiceModal = false"
                                class="px-4 py-2 rounded-xl border border-slate-300 text-xs font-bold text-slate-700 hover:bg-slate-50 cursor-pointer">
                            Batal
                        </button>
                    </div>
                </div>

                {{-- Form Import File Excel di dalam Modal Pilihan Awal --}}
                <div x-show="createChoiceStep === 'import'" x-cloak>
                    @include('admin.schedules.partials.import-schedule-form', [
                        'academicYear' => $academicYear,
                        'cancelClick' => "createChoiceStep = 'choose'",
                        'cancelLabel' => '← Kembali ke Pilihan Metode',
                        'submitLabel' => 'Buat Jadwal Baru dari File Excel',
                    ])
                </div>
            </div>
        </div>
    </div>

    {{-- MODAL QUICK EDIT SEL JADWAL --}}
    <div x-show="editModal" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100">
        <div class="fixed inset-0 bg-slate-950/70 backdrop-blur-xs" @click="editModal = false"></div>
        <div class="min-h-full flex items-center justify-center p-4">
            <div class="relative bg-white rounded-3xl shadow-2xl max-w-md w-full p-6 overflow-hidden border border-slate-100"
                 @click.stop>
                <div class="flex items-start justify-between pb-3 border-b border-slate-100 mb-4">
                    <div>
                        <span class="text-[11px] font-bold text-emerald-700" x-text="selectedItem ? ('Kelas ' + selectedItem.class_name + ' &bull; Jam ' + selectedItem.period_order + ' (' + selectedItem.time_range + ')') : ''"></span>
                        <h3 class="text-base font-extrabold text-slate-900">Edit Guru & Mapel Jadwal</h3>
                    </div>
                    <button type="button" @click="editModal = false" class="text-slate-400 hover:text-slate-600 p-1">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <template x-if="selectedItem">
                    <form method="POST" :action="'/admin/jadwal-master/item/' + selectedItem.id" class="space-y-4">
                        @csrf
                        @method('PUT')

                        {{-- Alert Jika Sedang Bentrok --}}
                        <div x-show="selectedItem.is_conflict" class="p-3 rounded-xl bg-rose-50 border border-rose-200 text-xs text-rose-800">
                            <strong class="font-bold">Status Bentrok:</strong>
                            <p class="mt-0.5" x-text="selectedItem.conflict_notes"></p>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Pilih Kode Guru / Kegiatan</label>
                            <select name="teacher_code" x-model="selectedTeacherCode" @change="onTeacherSelect($event.target.value)"
                                    class="w-full rounded-xl border border-slate-300 px-3 py-2 text-xs font-bold focus:ring-emerald-500 focus:border-emerald-500">
                                <option value="">-- Kosongkan Sel Jadwal --</option>
                                <optgroup label="Kode Mengajar Resmi (Master Guru — {{ $teachers->count() }} Penugasan)">
                                    <template x-for="t in teachersList" :key="t.code">
                                        <option :value="t.code" :selected="String(t.code) === String(selectedTeacherCode)"
                                                x-text="'Kode ' + t.code + ' — ' + t.name + ' (' + (t.subject || '-') + ')'"></option>
                                    </template>
                                </optgroup>
                                <optgroup label="Kegiatan Sekolah / Pembiasaan">
                                    <option value="UPACARA BENDERA">UPACARA BENDERA</option>
                                    <option value="SHOLAT DHUHA">SHOLAT DHUHA</option>
                                    <option value="ISTIRAHAT">ISTIRAHAT</option>
                                    <option value="KEGIATAN KEAGAMAAN">KEGIATAN KEAGAMAAN</option>
                                    <option value="SABTU BERSIH / OR">SABTU BERSIH / OR</option>
                                </optgroup>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Nama Guru (Otomatis dari Master Guru)</label>
                            <input type="text" name="teacher_name" x-model="selectedTeacherName" readonly
                                   class="w-full rounded-xl bg-slate-100 text-slate-600 border border-slate-300 px-3 py-2 text-xs font-semibold">
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Mata Pelajaran (Otomatis dari Master Guru)</label>
                            <input type="text" name="subject_name" x-model="selectedSubjectName" readonly
                                   class="w-full rounded-xl bg-slate-100 text-slate-600 border border-slate-300 px-3 py-2 text-xs font-semibold">
                        </div>

                        <div class="pt-3 flex items-center justify-between border-t border-slate-100">
                            <button type="button" @click="openSwap(selectedItem); editModal = false"
                                    x-show="!isCommonActivity(selectedItem)"
                                    class="px-3 py-2 rounded-xl bg-amber-100 hover:bg-amber-200 text-amber-900 font-bold text-xs transition">
                                ⇄ Tukar dengan Sel Lain
                            </button>
                            <div class="flex items-center gap-2">
                                <button type="button" @click="editModal = false"
                                        class="px-3.5 py-2 rounded-xl border border-slate-300 text-xs font-bold text-slate-700 hover:bg-slate-50">
                                    Batal
                                </button>
                                <button type="submit"
                                        class="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold shadow-xs">
                                    Simpan Perubahan
                                </button>
                            </div>
                        </div>
                    </form>
                </template>
            </div>
        </div>
    </div>

    {{-- MODAL TUKAR JADWAL (SWAP SLOTS) --}}
    <div x-show="swapModal" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100">
        <div class="fixed inset-0 bg-slate-950/70 backdrop-blur-xs" @click="swapModal = false"></div>
        <div class="min-h-full flex items-center justify-center p-4">
            <div class="relative bg-white rounded-3xl shadow-2xl max-w-lg w-full p-6 overflow-hidden border border-slate-100"
                 @click.stop>
                <div class="flex items-start justify-between pb-3 border-b border-slate-100 mb-4">
                    <div>
                        <span class="text-[11px] font-bold text-amber-700 flex items-center gap-1.5">
                            <span>⚡ Pengaturan Fleksibel &amp; Anti-Bentrok</span>
                        </span>
                        <h3 class="text-base font-extrabold text-slate-900">Tukar Jadwal (Swap Slot)</h3>
                    </div>
                    <button type="button" @click="swapModal = false" class="text-slate-400 hover:text-slate-600 p-1 cursor-pointer">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <template x-if="swapItem1">
                    <form method="POST" action="{{ route('admin.schedules.master.swap') }}" class="space-y-4">
                        @csrf
                        <input type="hidden" name="item_id_1" :value="swapItem1.id">

                        {{-- Slot 1 Terpilih --}}
                        <div class="p-3.5 rounded-2xl bg-amber-50 border border-amber-200 text-xs space-y-1">
                            <span class="font-extrabold text-amber-900 uppercase tracking-wider text-[10px] block">Slot 1 yang Dipilih:</span>
                            <div class="font-black text-slate-900 text-sm flex items-center gap-2">
                                <span class="px-2 py-0.5 rounded-lg bg-amber-200/80 text-amber-950 font-black" x-text="'Kelas ' + swapItem1.class_name"></span>
                                <span x-text="(swapItem1.day_name || ('Hari ' + swapItem1.day_of_week)) + ' &bull; Jam ' + swapItem1.period_order + ' (' + swapItem1.time_range + ')'"></span>
                            </div>
                            <div class="text-[11px] text-slate-600 pt-0.5">
                                Isi: <strong class="text-slate-800" x-text="swapItem1.teacher_code ? ('Kode ' + swapItem1.teacher_code + ' (' + (swapItem1.teacher_name || '-') + (swapItem1.subject_name ? ' - ' + swapItem1.subject_name : '') + ')') : '⚪ Slot Kosong'"></strong>
                            </div>
                        </div>

                        {{-- Jaminan Konsistensi JP --}}
                        <div class="p-3 rounded-2xl bg-emerald-50 border border-emerald-200 text-xs text-emerald-950 space-y-1">
                            <div class="font-bold flex items-center gap-1.5 text-emerald-900">
                                <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                                <span>Jumlah JP Kelas Dijamin Aman &amp; Konsisten</span>
                            </div>
                            <p class="text-[11px] text-emerald-800 leading-relaxed">
                                Target penukaran khusus dibatasi untuk <strong>Kelas <span x-text="swapItem1.class_name"></span></strong> saja (bisa hari yang sama atau berbeda hari). Hal ini memastikan total JP per mapel dan beban belajar kelas tetap persis sama.
                            </p>
                        </div>

                        {{-- Filter Hari Cepat --}}
                        <div class="space-y-1.5">
                            <div class="flex items-center justify-between">
                                <label class="block text-xs font-bold text-slate-800">
                                    Pilih Slot Target di Kelas <span class="text-amber-800 font-extrabold" x-text="swapItem1.class_name"></span>:
                                </label>
                                <span class="text-[10px] text-slate-400 font-bold" x-show="!loadingSwapTargets"
                                      x-text="filteredSwapTargets.length + ' Slot Tersedia'"></span>
                            </div>

                            <div class="flex items-center gap-1 flex-wrap">
                                <button type="button" @click="swapFilterDay = 'all'"
                                        class="px-2.5 py-1 rounded-lg text-[10px] font-bold border transition cursor-pointer"
                                        :class="swapFilterDay === 'all' ? 'bg-amber-600 text-white border-amber-600 shadow-2xs' : 'bg-slate-50 text-slate-600 border-slate-200 hover:bg-slate-100'">
                                    Semua Hari
                                </button>
                                @foreach([1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => "Jum'at", 6 => 'Sabtu'] as $dNum => $dName)
                                    <button type="button" @click="swapFilterDay = '{{ $dNum }}'"
                                            class="px-2.5 py-1 rounded-lg text-[10px] font-bold border transition cursor-pointer"
                                            :class="swapFilterDay === '{{ $dNum }}' ? 'bg-amber-600 text-white border-amber-600 shadow-2xs' : 'bg-slate-50 text-slate-600 border-slate-200 hover:bg-slate-100'">
                                        {{ $dName }}
                                    </button>
                                @endforeach
                            </div>
                        </div>

                        {{-- Dropdown Pilihan Slot Target --}}
                        <div>
                            {{-- State Loading --}}
                            <div x-show="loadingSwapTargets" class="py-4 text-center text-xs text-slate-400 font-bold flex items-center justify-center gap-2">
                                <svg class="animate-spin w-4 h-4 text-amber-600" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                                <span>Memuat slot Kelas <span x-text="swapItem1.class_name"></span> dari database...</span>
                            </div>

                            {{-- Select Dropdown --}}
                            <div x-show="!loadingSwapTargets">
                                <select name="item_id_2" required x-model="swapItem2Id"
                                        class="w-full rounded-xl border border-slate-300 px-3.5 py-2.5 text-xs font-bold bg-white focus:ring-amber-500 focus:border-amber-500">
                                    <option value="">-- Pilih Slot Target di Kelas <span x-text="swapItem1.class_name"></span> --</option>
                                    <template x-for="target in filteredSwapTargets" :key="target.id">
                                        <option :value="target.id" x-text="target.label"></option>
                                    </template>
                                </select>
                                <p x-show="filteredSwapTargets.length === 0" class="text-[11px] text-slate-400 italic mt-1.5">
                                    Tidak ada slot target yang tersedia pada filter hari yang dipilih.
                                </p>
                            </div>
                        </div>

                        <div class="pt-3 flex items-center justify-end gap-2 border-t border-slate-100">
                            <button type="button" @click="swapModal = false"
                                    class="px-4 py-2 rounded-xl border border-slate-300 text-xs font-bold text-slate-700 hover:bg-slate-50 cursor-pointer">
                                Batal
                            </button>
                            <button type="submit"
                                    :disabled="!swapItem2Id"
                                    class="px-5 py-2.5 rounded-xl bg-amber-600 hover:bg-amber-700 disabled:opacity-50 disabled:cursor-not-allowed text-white text-xs font-extrabold shadow-xs cursor-pointer transition">
                                Lakukan Penukaran Slot
                            </button>
                        </div>
                    </form>
                </template>
            </div>
        </div>
    </div>

    {{-- LEGENDA SEMUA KODE GURU, WALI KELAS & GURU PIKET (TERBUKA & BISA DISOROT KE MATRIKS) --}}
    <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-2xs space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3 cursor-pointer" @click="openLegend = !openLegend">
            <div class="flex items-center gap-2 flex-wrap">
                <span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span>
                <h3 class="text-sm font-extrabold text-slate-900">Semua Kode Guru, Wali Kelas &amp; Guru Piket (Sinkron Master Guru)</h3>
                <span class="px-2 py-0.5 rounded-md bg-slate-100 text-slate-600 text-xs font-bold">{{ $teachers->count() }} Kode</span>
                <span class="text-[11px] text-slate-500 font-medium hidden sm:inline">&bull; Klik kartu kode guru untuk menyorot seluruh jam mengajarnya di matriks</span>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('admin.teachers.index') }}" @click.stop class="text-xs font-bold text-indigo-700 hover:underline">
                    Kelola di Master Guru &rarr;
                </a>
                <button type="button" class="text-xs font-bold text-emerald-700 hover:underline cursor-pointer">
                    <span x-text="openLegend ? 'Sembunyikan' : 'Buka Semua Kode Guru &darr;'"></span>
                </button>
            </div>
        </div>

        <div x-show="openLegend" x-collapse class="pt-3 border-t border-slate-100 space-y-3">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div class="relative w-full sm:w-72">
                    <input type="text" x-model="teacherSearch"
                           placeholder="Cari kode / nama guru / mapel..."
                           class="w-full rounded-xl border border-slate-300 px-3 py-1.5 text-xs focus:ring-emerald-500 focus:border-emerald-500">
                </div>
                <span class="text-[11px] text-slate-500 font-semibold"
                      x-text="'Menampilkan ' + filteredTeachersLegend.length + ' dari ' + teachersList.length + ' kode guru'"></span>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-2 text-xs">
                <template x-for="t in filteredTeachersLegend" :key="'leg_' + t.code">
                    <button type="button"
                            @click="toggleTeacherHighlight(t.code)"
                            class="text-left p-2.5 rounded-xl border flex items-center justify-between gap-2 transition cursor-pointer"
                            :class="highlightedTeacherCode === normCode(t.code) ? 'bg-sky-50 border-sky-400 ring-2 ring-sky-400' : 'bg-slate-50 border-slate-200 hover:border-emerald-400 hover:bg-emerald-50/40'">
                        <div class="flex items-center gap-2.5 min-w-0">
                            <span class="w-8 h-8 rounded-lg text-white font-extrabold flex items-center justify-center text-xs shrink-0"
                                  :class="highlightedTeacherCode === normCode(t.code) ? 'bg-sky-600' : 'bg-emerald-600'"
                                  x-text="t.code"></span>
                            <div class="min-w-0">
                                <div class="font-bold text-slate-900 truncate" x-text="t.name"></div>
                                <div class="text-[11px] text-slate-500 truncate" x-text="t.subject || '-'"></div>
                            </div>
                        </div>
                        <div class="flex flex-col items-end gap-1 shrink-0">
                            <template x-if="t.homeroom_class">
                                <span class="px-2 py-0.5 rounded bg-sky-100 text-sky-800 text-[10px] font-bold"
                                      x-text="'Wali ' + t.homeroom_class"></span>
                            </template>
                            <template x-if="t.piket_day">
                                <span class="px-2 py-0.5 rounded bg-emerald-100 text-emerald-800 text-[10px] font-bold"
                                      x-text="'Piket ' + t.piket_day"></span>
                            </template>
                        </div>
                    </button>
                </template>
            </div>
        </div>
    </div>

    {{-- Modal Import Master Jadwal (Excel / CSV) --}}
    <div x-show="importScheduleModal" x-cloak
         @keydown.escape.window="importScheduleModal = false"
         class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-xs p-4">
        <div @click.outside="importScheduleModal = false"
             class="w-full max-w-lg rounded-2xl bg-white shadow-2xl border border-slate-200 overflow-hidden">
            <div class="flex items-center justify-between px-5 py-4 border-b border-slate-200 bg-slate-50">
                <div>
                    <h3 class="text-base font-extrabold text-slate-800">Import Master Jadwal (Excel / CSV)</h3>
                    <p class="text-xs text-slate-500 mt-0.5">Mendukung format 1 Shift maupun 2 Shift berdampingan.</p>
                </div>
                <button type="button" @click="importScheduleModal = false" class="p-1.5 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-200/70 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <div class="p-5">
                @include('admin.schedules.partials.import-schedule-form', [
                    'academicYear' => $academicYear,
                    'cancelClick' => 'importScheduleModal = false',
                    'cancelLabel' => 'Batal',
                    'submitLabel' => 'Mulai Import Jadwal',
                ])
            </div>
        </div>
    </div>

</div>
@endsection
