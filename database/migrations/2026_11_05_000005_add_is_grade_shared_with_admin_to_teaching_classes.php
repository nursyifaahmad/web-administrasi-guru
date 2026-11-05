<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('teaching_classes', 'is_grade_shared_with_admin')) {
            Schema::table('teaching_classes', function (Blueprint $table) {
                $table->boolean('is_grade_shared_with_admin')->default(false)->after('only_teaching_days');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('teaching_classes', 'is_grade_shared_with_admin')) {
            Schema::table('teaching_classes', function (Blueprint $table) {
                $table->dropColumn('is_grade_shared_with_admin');
            });
        }
    }
};
