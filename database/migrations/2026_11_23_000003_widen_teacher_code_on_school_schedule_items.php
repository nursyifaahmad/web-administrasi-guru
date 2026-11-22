<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('school_schedule_items') && Schema::hasColumn('school_schedule_items', 'teacher_code')) {
            Schema::table('school_schedule_items', function (Blueprint $table) {
                $table->string('teacher_code', 120)->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('school_schedule_items') && Schema::hasColumn('school_schedule_items', 'teacher_code')) {
            Schema::table('school_schedule_items', function (Blueprint $table) {
                $table->string('teacher_code', 20)->nullable()->change();
            });
        }
    }
};
