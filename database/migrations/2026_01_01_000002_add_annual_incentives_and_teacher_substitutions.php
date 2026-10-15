<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table): void {
            $table->enum('status', ['HADIR', 'IZIN', 'ALFA', 'SAKIT'])->default('HADIR')->change();
        });

        Schema::table('branch_incentives', function (Blueprint $table): void {
            $table->enum('calculation_type', ['ANNUAL', 'MANUAL'])->default('MANUAL')->after('name');
        });

        Schema::create('annual_incentive_assignments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('branch_id')->index();
            $table->uuid('academic_year_id')->index();
            $table->uuid('employee_id')->index();
            $table->uuid('branch_incentive_id')->index();
            $table->decimal('amount', 15, 2)->default(0);
            $table->timestamps();
            $table->timestamp('synced_at')->nullable();
            $table->unique(
                ['academic_year_id', 'employee_id', 'branch_incentive_id'],
                'annual_incentive_assignment_unique'
            );
            $table->foreign('branch_id')->references('id')->on('branches')->cascadeOnDelete();
            $table->foreign('academic_year_id')->references('id')->on('academic_years')->cascadeOnDelete();
            $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();
            $table->foreign('branch_incentive_id')->references('id')->on('branch_incentives')->cascadeOnDelete();
        });

        Schema::create('teacher_substitutions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('branch_id')->index();
            $table->uuid('roster_slot_id')->index();
            $table->uuid('absent_employee_id')->index();
            $table->uuid('substitute_employee_id')->index();
            $table->date('date');
            $table->unsignedTinyInteger('period');
            $table->boolean('is_cancelled')->default(false);
            $table->timestamps();
            $table->timestamp('synced_at')->nullable();
            $table->unique(['roster_slot_id', 'date'], 'teacher_substitution_slot_date_unique');
            $table->index(['branch_id', 'date', 'period', 'substitute_employee_id'], 'teacher_substitute_available_slot_index');
            $table->foreign('branch_id')->references('id')->on('branches')->cascadeOnDelete();
            $table->foreign('absent_employee_id')->references('id')->on('employees')->cascadeOnDelete();
            $table->foreign('substitute_employee_id')->references('id')->on('employees')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teacher_substitutions');
        Schema::dropIfExists('annual_incentive_assignments');

        Schema::table('branch_incentives', function (Blueprint $table): void {
            $table->dropColumn('calculation_type');
        });

        Schema::table('attendances', function (Blueprint $table): void {
            $table->enum('status', ['HADIR', 'IZIN', 'ALFA'])->default('HADIR')->change();
        });
    }
};
