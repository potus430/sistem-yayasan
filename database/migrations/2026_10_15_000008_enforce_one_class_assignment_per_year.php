<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_position_assignments', function (Blueprint $table): void {
            $table->string('assigned_class_name', 50)
                ->nullable()
                ->storedAs('CASE WHEN class_name IS NOT NULL THEN class_name ELSE NULL END');
            $table->unique(
                ['branch_id', 'academic_year_id', 'assigned_class_name'],
                'branch_year_one_class_assignment_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('employee_position_assignments', function (Blueprint $table): void {
            $table->dropUnique('branch_year_one_class_assignment_unique');
            $table->dropColumn('assigned_class_name');
        });
    }
};
