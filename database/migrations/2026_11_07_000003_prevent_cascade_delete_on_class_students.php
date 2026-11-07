<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Pada tabel class_students, lepas foreign key cascade lama dari teaching_class_id
        // Murid adalah data milik Rombel (school_class_id), bukan milik salah satu guru pengampu (teaching_class_id).
        if (DB::getDriverName() === 'mysql') {
            $foreignKeys = DB::select("
                SELECT CONSTRAINT_NAME 
                FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
                WHERE TABLE_NAME = 'class_students' 
                AND COLUMN_NAME = 'teaching_class_id' 
                AND REFERENCED_TABLE_NAME = 'teaching_classes'
                AND CONSTRAINT_SCHEMA = DATABASE()
            ");

            if (! empty($foreignKeys)) {
                Schema::table('class_students', function (Blueprint $table) {
                    $table->dropForeign(['teaching_class_id']);
                });
            }

            Schema::table('class_students', function (Blueprint $table) {
                $table->foreign('teaching_class_id')
                    ->references('id')
                    ->on('teaching_classes')
                    ->nullOnDelete();
            });
        } elseif (DB::getDriverName() === 'sqlite') {
            try {
                Schema::table('class_students', function (Blueprint $table) {
                    $table->dropForeign(['teaching_class_id']);
                });
            } catch (\Throwable $e) {
                // SQLite in-memory fallback
            }
        }

        // 2. Putuskan seluruh ketergantungan data murid yang ada dari teaching_class_id
        DB::table('class_students')->update(['teaching_class_id' => null]);
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            Schema::table('class_students', function (Blueprint $table) {
                $table->dropForeign(['teaching_class_id']);
                $table->foreign('teaching_class_id')
                    ->references('id')
                    ->on('teaching_classes')
                    ->cascadeOnDelete();
            });
        }
    }
};
