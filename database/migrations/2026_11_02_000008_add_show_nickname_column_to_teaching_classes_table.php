<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('teaching_classes', 'show_nickname_column')) {
            Schema::table('teaching_classes', function (Blueprint $table) {
                $table->boolean('show_nickname_column')->default(true)->after('schedule_info');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('teaching_classes', 'show_nickname_column')) {
            Schema::table('teaching_classes', function (Blueprint $table) {
                $table->dropColumn('show_nickname_column');
            });
        }
    }
};
