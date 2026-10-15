<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('branches')) {
            Schema::create('branches', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('name');
                $table->enum('level', ['TK', 'SD', 'SMP', 'SMA']);
                $table->text('address')->nullable();
                $table->timestamps();
            });
        }

        Schema::create('academic_years', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name', 20)->unique();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });

        Schema::create('employees', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('branch_id')->index();
            $table->string('nip', 50);
            $table->string('name');
            $table->enum('type', ['GURU', 'STAFF']);
            $table->decimal('base_salary', 15, 2)->default(0);
            $table->enum('status', ['TETAP', 'HONORER'])->default('TETAP');
            $table->timestamps();
            $table->timestamp('synced_at')->nullable();
            $table->unique(['branch_id', 'nip']);
            $table->foreign('branch_id')->references('id')->on('branches')->cascadeOnDelete();
        });

        Schema::create('students', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('branch_id')->index();
            $table->string('nisn', 30);
            $table->string('name');
            $table->string('current_class', 50);
            $table->timestamps();
            $table->timestamp('synced_at')->nullable();
            $table->unique(['branch_id', 'nisn']);
            $table->foreign('branch_id')->references('id')->on('branches')->cascadeOnDelete();
        });

        Schema::create('salaries', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('employee_id')->index();
            $table->uuid('branch_id')->index();
            $table->string('period', 7);
            $table->decimal('incentives', 15, 2)->default(0);
            $table->decimal('allowances', 15, 2)->default(0);
            $table->decimal('deductions', 15, 2)->default(0);
            $table->decimal('net_salary', 15, 2)->default(0);
            $table->enum('status', ['PENDING', 'PAID'])->default('PENDING');
            $table->timestamps();
            $table->timestamp('synced_at')->nullable();
            $table->unique(['employee_id', 'period']);
            $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();
            $table->foreign('branch_id')->references('id')->on('branches')->cascadeOnDelete();
        });

        Schema::create('spp_billings', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('student_id')->index();
            $table->uuid('branch_id')->index();
            $table->uuid('academic_year_id')->index();
            $table->unsignedTinyInteger('month');
            $table->decimal('amount', 15, 2);
            $table->enum('status', ['UNPAID', 'PAID'])->default('UNPAID');
            $table->timestamps();
            $table->timestamp('synced_at')->nullable();
            $table->unique(['student_id', 'academic_year_id', 'month']);
            $table->foreign('student_id')->references('id')->on('students')->cascadeOnDelete();
            $table->foreign('branch_id')->references('id')->on('branches')->cascadeOnDelete();
            $table->foreign('academic_year_id')->references('id')->on('academic_years')->cascadeOnDelete();
        });

        Schema::create('attendances', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('employee_id')->index();
            $table->uuid('branch_id')->index();
            $table->date('date');
            $table->enum('status', ['HADIR', 'IZIN', 'ALFA']);
            $table->timestamps();
            $table->timestamp('synced_at')->nullable();
            $table->unique(['employee_id', 'date']);
            $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();
            $table->foreign('branch_id')->references('id')->on('branches')->cascadeOnDelete();
        });

        Schema::create('roster_slots', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('branch_id')->index();
            $table->uuid('academic_year_id')->index();
            $table->uuid('employee_id')->index();
            $table->string('subject', 100);
            $table->string('class_name', 50);
            $table->string('room', 50);
            $table->unsignedTinyInteger('weekday');
            $table->unsignedTinyInteger('period');
            $table->timestamps();
            $table->timestamp('synced_at')->nullable();
            $table->unique(['branch_id', 'academic_year_id', 'weekday', 'period', 'class_name'], 'roster_class_slot_unique');
            $table->unique(['branch_id', 'academic_year_id', 'weekday', 'period', 'employee_id'], 'roster_teacher_slot_unique');
            $table->unique(['branch_id', 'academic_year_id', 'weekday', 'period', 'room'], 'roster_room_slot_unique');
            $table->foreign('branch_id')->references('id')->on('branches')->cascadeOnDelete();
            $table->foreign('academic_year_id')->references('id')->on('academic_years')->cascadeOnDelete();
            $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roster_slots');
        Schema::dropIfExists('attendances');
        Schema::dropIfExists('spp_billings');
        Schema::dropIfExists('salaries');
        Schema::dropIfExists('students');
        Schema::dropIfExists('employees');
        Schema::dropIfExists('academic_years');
        Schema::dropIfExists('branches');
    }
};
