<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->deduplicateExistingJournals();

        if (! Schema::hasIndex('daily_journals', 'uniq_daily_journals_class_date')) {
            Schema::table('daily_journals', function (Blueprint $table) {
                $table->unique(['teaching_class_id', 'journal_date'], 'uniq_daily_journals_class_date');
            });
        }

        if (Schema::hasIndex('daily_journals', 'daily_journals_teaching_class_id_journal_date_index')) {
            Schema::table('daily_journals', function (Blueprint $table) {
                $table->dropIndex('daily_journals_teaching_class_id_journal_date_index');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasIndex('daily_journals', 'daily_journals_teaching_class_id_journal_date_index')) {
            Schema::table('daily_journals', function (Blueprint $table) {
                $table->index(['teaching_class_id', 'journal_date'], 'daily_journals_teaching_class_id_journal_date_index');
            });
        }

        if (Schema::hasIndex('daily_journals', 'uniq_daily_journals_class_date')) {
            Schema::table('daily_journals', function (Blueprint $table) {
                $table->dropUnique('uniq_daily_journals_class_date');
            });
        }
    }

    /**
     * Rapikan duplikat historis pada pasangan (teaching_class_id, journal_date) sebelum memasang UNIQUE constraint.
     */
    protected function deduplicateExistingJournals(): void
    {
        $rows = DB::table('daily_journals')
            ->orderBy('teaching_class_id')
            ->orderBy('id')
            ->get();

        if ($rows->count() <= 1) {
            return;
        }

        $grouped = [];
        foreach ($rows as $row) {
            try {
                $dateKey = Carbon::parse((string) $row->journal_date)->toDateString();
            } catch (\Throwable) {
                $dateKey = trim((string) $row->journal_date);
            }

            $key = $row->teaching_class_id . '|' . $dateKey;
            $grouped[$key][] = $row;
        }

        DB::transaction(function () use ($grouped) {
            foreach ($grouped as $items) {
                if (count($items) <= 1) {
                    continue;
                }

                usort($items, function ($a, $b) {
                    $scoreDiff = $this->scoreJournalRow($b) <=> $this->scoreJournalRow($a);
                    if ($scoreDiff !== 0) {
                        return $scoreDiff;
                    }

                    return (int) $a->id <=> (int) $b->id;
                });

                $keeper = $items[0];
                $duplicates = array_slice($items, 1);

                $mergedMaterial = trim((string) ($keeper->activity_material ?? ''));
                $mergedNotes = trim((string) ($keeper->notes ?? ''));
                $mergedSig = $keeper->signature_data;
                $deleteIds = [];

                foreach ($duplicates as $dup) {
                    $deleteIds[] = (int) $dup->id;

                    $dupMat = trim((string) ($dup->activity_material ?? ''));
                    if (mb_strlen($dupMat) >= 3 && ! str_contains(mb_strtolower($mergedMaterial), mb_strtolower($dupMat))) {
                        $mergedMaterial = $mergedMaterial !== '' ? ($mergedMaterial . "\n" . $dupMat) : $dupMat;
                    }

                    $dupNotes = trim((string) ($dup->notes ?? ''));
                    if (mb_strlen($dupNotes) >= 2 && $dupNotes !== '-' && ! str_contains(mb_strtolower($mergedNotes), mb_strtolower($dupNotes))) {
                        $mergedNotes = ($mergedNotes !== '' && $mergedNotes !== '-') ? ($mergedNotes . "\n" . $dupNotes) : $dupNotes;
                    }

                    if (empty($mergedSig) && ! empty($dup->signature_data)) {
                        $mergedSig = $dup->signature_data;
                    }
                }

                DB::table('daily_journals')
                    ->where('id', $keeper->id)
                    ->update([
                        'activity_material' => $mergedMaterial !== '' ? $mergedMaterial : $keeper->activity_material,
                        'notes' => $mergedNotes !== '' ? $mergedNotes : null,
                        'signature_data' => $mergedSig,
                    ]);

                DB::table('daily_journals')->whereIn('id', $deleteIds)->delete();
            }
        });
    }

    protected function scoreJournalRow(object $row): int
    {
        $material = trim((string) ($row->activity_material ?? ''));
        $matLen = mb_strlen($material);
        $notes = trim((string) ($row->notes ?? ''));

        $score = ($matLen >= 3 ? 10000 : 0) + min($matLen, 5000);
        if ($notes !== '' && $notes !== '-') {
            $score += 500 + min(mb_strlen($notes), 500);
        }
        if (! empty($row->signature_data)) {
            $score += 200;
        }
        if (! (bool) ($row->is_attendance_overridden ?? false)) {
            $score += 50;
        }

        return $score;
    }
};
