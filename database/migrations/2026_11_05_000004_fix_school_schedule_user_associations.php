<?php

use App\Models\AcademicYear;
use App\Models\SchoolScheduleItem;
use App\Models\User;
use App\Services\SchoolScheduleService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('school_schedule_teachers')) {
            return;
        }

        try {
            // 1. Bersihkan asosiasi user_id akun Admin dari tabel jadwal guru & item
            $adminIds = User::query()
                ->where('role', 'admin')
                ->orWhere('name', 'like', '%Administrator%')
                ->orWhere('name', 'like', '%Admin%')
                ->pluck('id')
                ->all();

            if (! empty($adminIds)) {
                DB::table('school_schedule_teachers')->whereIn('user_id', $adminIds)->update(['user_id' => null]);
                SchoolScheduleItem::query()->whereIn('user_id', $adminIds)->update(['user_id' => null]);
            }

            // 2. Hubungkan ulang akun guru (hanya role guru) ke kode guru jadwal resmi
            $guruUsers = User::query()
                ->where('is_active', true)
                ->where(function ($q) {
                    $q->where('role', 'guru')->orWhereNull('role');
                })
                ->whereNotIn('id', $adminIds)
                ->get();

            $activeYear = AcademicYear::query()->where('is_active', true)->first();
            if ($activeYear) {
                $teachers = DB::table('school_schedule_teachers')
                    ->where('academic_year_id', $activeYear->id)
                    ->get();

                foreach ($teachers as $t) {
                    $cleanT = SchoolScheduleService::cleanAlphaString($t->name);
                    $pureT = SchoolScheduleService::cleanAlphaString(explode(',', $t->name)[0]);

                    if (strlen($cleanT) < 4 || strlen($pureT) < 4) {
                        continue;
                    }

                    $matched = $guruUsers->first(function ($u) use ($cleanT, $pureT) {
                        $cleanU = SchoolScheduleService::cleanAlphaString($u->name);
                        $pureU = SchoolScheduleService::cleanAlphaString(explode(',', $u->name)[0]);

                        if (strlen($cleanU) < 4 || strlen($pureU) < 4) {
                            return false;
                        }

                        if ($cleanU === $cleanT || $pureU === $pureT) {
                            return true;
                        }

                        return (strlen($pureU) >= 4 && strlen($pureT) >= 4)
                            && ($pureU === $pureT || str_contains($pureT, $pureU) || str_contains($pureU, $pureT));
                    });

                    if ($matched) {
                        DB::table('school_schedule_teachers')->where('id', $t->id)->update(['user_id' => $matched->id]);
                        SchoolScheduleItem::query()
                            ->where('academic_year_id', $activeYear->id)
                            ->where('teacher_code', $t->code)
                            ->update(['user_id' => $matched->id]);
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Fix school schedule user associations migration skipped/errored: ' . $e->getMessage());
        }
    }

    public function down(): void
    {
    }
};
