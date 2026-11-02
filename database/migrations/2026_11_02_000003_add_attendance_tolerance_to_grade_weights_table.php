<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('grade_weights', function (Blueprint $table) {
            $table->string('attendance_mode', 16)->default('manual')->after('rounding_precision');
            $table->unsignedSmallInteger('sick_leave_tolerance')->default(3)->after('attendance_mode');
        });
    }

    public function down(): void
    {
        Schema::table('grade_weights', function (Blueprint $table) {
            $table->dropColumn(['attendance_mode', 'sick_leave_tolerance']);
        });
    }
};
