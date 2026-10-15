<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_position_assignments', function (Blueprint $table): void {
            $table->uuid('single_position_employee_id')
                ->nullable()
                ->storedAs('CASE WHEN class_name IS NULL THEN employee_id ELSE NULL END');
            $table->uuid('class_teacher_employee_id')
                ->nullable()
                ->storedAs('CASE WHEN class_name IS NOT NULL THEN employee_id ELSE NULL END');
            $table->unique(
                ['branch_id', 'academic_year_id', 'single_position_employee_id'],
                'employee_one_non_class_position_unique'
            );
            $table->unique(
                ['branch_id', 'academic_year_id', 'class_teacher_employee_id'],
                'employee_one_homeroom_class_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('employee_position_assignments', function (Blueprint $table): void {
            $table->dropUnique('employee_one_non_class_position_unique');
            $table->dropUnique('employee_one_homeroom_class_unique');
            $table->dropColumn(['single_position_employee_id', 'class_teacher_employee_id']);
        });
    }
};
