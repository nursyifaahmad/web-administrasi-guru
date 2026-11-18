<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teaching_classes', function (Blueprint $table) {
            if (! Schema::hasColumn('teaching_classes', 'is_student_share_anti_screenshot')) {
                $table->boolean('is_student_share_anti_screenshot')
                    ->default(true)
                    ->after('is_grade_shared_with_students');
            }
        });
    }

    public function down(): void
    {
        Schema::table('teaching_classes', function (Blueprint $table) {
            if (Schema::hasColumn('teaching_classes', 'is_student_share_anti_screenshot')) {
                $table->dropColumn('is_student_share_anti_screenshot');
            }
        });
    }
};
