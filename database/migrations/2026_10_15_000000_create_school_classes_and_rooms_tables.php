<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Ramsey\Uuid\Uuid;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_classes', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('branch_id')->index();
            $table->string('name', 50);
            $table->timestamps();
            $table->unique(['branch_id', 'name']);
            $table->foreign('branch_id')->references('id')->on('branches')->cascadeOnDelete();
        });

        Schema::create('school_rooms', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('branch_id')->index();
            $table->string('name', 50);
            $table->timestamps();
            $table->unique(['branch_id', 'name']);
            $table->foreign('branch_id')->references('id')->on('branches')->cascadeOnDelete();
        });

        foreach (DB::table('students')->select('branch_id', 'current_class')->distinct()->get() as $class) {
            DB::table('school_classes')->insert([
                'id' => Uuid::uuid7()->toString(),
                'branch_id' => $class->branch_id,
                'name' => $class->current_class,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        foreach (DB::table('roster_slots')->select('branch_id', 'class_name')->distinct()->get() as $class) {
            DB::table('school_classes')->insertOrIgnore([
                'id' => Uuid::uuid7()->toString(),
                'branch_id' => $class->branch_id,
                'name' => $class->class_name,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        foreach (DB::table('roster_slots')->select('branch_id', 'room')->distinct()->get() as $room) {
            DB::table('school_rooms')->insertOrIgnore([
                'id' => Uuid::uuid7()->toString(),
                'branch_id' => $room->branch_id,
                'name' => $room->room,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('school_rooms');
        Schema::dropIfExists('school_classes');
    }
};
