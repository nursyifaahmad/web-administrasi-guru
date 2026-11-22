@php
    $groups = $shift['groups'] ?? [];
    $days = $shift['days'] ?? [];
@endphp

<div class="shift-block">
    @if(!empty($shiftTitle))
        <div class="shift-banner {{ ($shift['shift_key'] ?? '') === 'siang' ? 'shift-banner-siang' : 'shift-banner-pagi' }}">
            {{ $shiftTitle }}
        </div>
    @endif

    <table class="sched-grid">
        <thead>
            <tr>
                <th rowspan="2" class="col-hari">HARI</th>
                <th rowspan="2" class="col-jp">JP<br>KE</th>
                <th rowspan="2" class="col-waktu">WAKTU</th>
                @forelse($groups as $grp)
                    <th colspan="{{ count($grp['classes']) }}" class="col-grade">{{ $grp['grade_label'] }}</th>
                @empty
                    <th class="col-grade">KELAS</th>
                @endforelse
            </tr>
            <tr>
                @forelse($groups as $grp)
                    @foreach($grp['classes'] as $clsMeta)
                        <th class="col-sec">{{ $clsMeta['section'] }}</th>
                    @endforeach
                @empty
                    <th class="col-sec">-</th>
                @endforelse
            </tr>
        </thead>
        <tbody>
            @forelse($days as $day)
                @php
                    $slots = $day['slots'] ?? [];
                    $slotCount = count($slots);
                @endphp
                @foreach($slots as $sIdx => $slot)
                    @php
                        $isLastDayRow = ($sIdx === $slotCount - 1);
                    @endphp
                    <tr class="{{ $isLastDayRow ? 'day-end-row' : '' }}">
                        @if($sIdx === 0)
                            <td rowspan="{{ $slotCount }}" class="cell-hari">
                                @if(!empty($isPdf))
                                    {!! implode('<br>', mb_str_split($day['day_name'])) !!}
                                @else
                                    <span class="hari-vertical">{{ $day['day_name'] }}</span>
                                @endif
                            </td>
                        @endif

                        <td class="cell-jp">{{ $slot['jp_label'] }}</td>
                        <td class="cell-waktu">{{ $slot['time_range'] }}</td>

                        @if(!empty($groups))
                            @foreach($groups as $gIdx => $grp)
                                @php
                                    $grpCell = $slot['group_cells'][$gIdx] ?? null;
                                @endphp
                                @if($grpCell && !empty($grpCell['is_activity']))
                                    <td colspan="{{ count($grp['classes']) }}"
                                        class="cell-activity {{ !empty($grpCell['is_highlighted']) ? 'bg-yellow' : '' }}">
                                        {{ $grpCell['activity_name'] }}
                                    </td>
                                @else
                                    @foreach($grp['classes'] as $clsMeta)
                                        @php
                                            $item = $slot['cells'][$clsMeta['code']] ?? null;
                                            $isConflict = $item && $item->is_conflict;
                                            $isCommon = $item && $item->isCommonActivity();
                                        @endphp
                                        <td class="cell-code {{ $isConflict ? 'bg-conflict' : ($isCommon ? 'cell-activity bg-yellow' : '') }}">{{ $item?->teacher_code ?? '' }}</td>
                                    @endforeach
                                @endif
                            @endforeach
                        @else
                            <td class="cell-code">-</td>
                        @endif
                    </tr>
                @endforeach
            @empty
                <tr>
                    <td colspan="{{ 3 + max(1, count($shift['flat_classes'] ?? [])) }}" style="padding: 16px; text-align: center; color: #64748b;">
                        Belum ada jadwal tersimpan pada shift ini.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
