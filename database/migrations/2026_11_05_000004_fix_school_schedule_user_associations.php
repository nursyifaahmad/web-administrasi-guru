<?php

use App\Models\AcademicYear;
use App\Models\SchoolScheduleItem;
use App\Models\SchoolScheduleTeacher;
use App\Models\User;
use App\Services\SchoolScheduleService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    public function up(): void
    {
        try {
            // 1. Bersihkan asosiasi user_id akun Admin dari tabel jadwal guru & item
            $adminIds = User::query()
                ->where('role', 'admin')
                ->orWhere('name', 'like', '%Administrator%')
                ->orWhere('name', 'like', '%Admin%')
                ->pluck('id')
                ->all();

            if (! empty($adminIds)) {
                SchoolScheduleTeacher::query()->whereIn('user_id', $adminIds)->update(['user_id' => null]);
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
                $teachers = SchoolScheduleTeacher::query()
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
                        $t->update(['user_id' => $matched->id]);
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
