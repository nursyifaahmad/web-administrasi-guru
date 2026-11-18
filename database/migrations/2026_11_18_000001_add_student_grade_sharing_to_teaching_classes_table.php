<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teaching_classes', function (Blueprint $table) {
            if (! Schema::hasColumn('teaching_classes', 'is_grade_shared_with_students')) {
                $table->boolean('is_grade_shared_with_students')->default(false)->after('is_grade_shared_with_admin');
            }
            if (! Schema::hasColumn('teaching_classes', 'student_share_slug')) {
                $table->string('student_share_slug', 120)->nullable()->unique()->after('is_grade_shared_with_students');
            }
            if (! Schema::hasColumn('teaching_classes', 'student_share_enabled_at')) {
                $table->timestamp('student_share_enabled_at')->nullable()->after('student_share_slug');
            }
        });
    }

    public function down(): void
    {
        Schema::table('teaching_classes', function (Blueprint $table) {
            if (Schema::hasColumn('teaching_classes', 'student_share_enabled_at')) {
                $table->dropColumn('student_share_enabled_at');
            }
            if (Schema::hasColumn('teaching_classes', 'student_share_slug')) {
                $table->dropUnique(['student_share_slug']);
                $table->dropColumn('student_share_slug');
            }
            if (Schema::hasColumn('teaching_classes', 'is_grade_shared_with_students')) {
                $table->dropColumn('is_grade_shared_with_students');
            }
        });
    }
};
