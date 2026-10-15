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
        Schema::table('branches', function (Blueprint $table): void {
            $table->string('leader_name')->nullable()->after('address');
            $table->string('leader_title', 100)->nullable()->after('leader_name');
            $table->string('phone', 40)->nullable()->after('leader_title');
        });

        Schema::create('school_subjects', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('branch_id')->index();
            $table->string('name', 100);
            $table->timestamps();
            $table->unique(['branch_id', 'name']);
            $table->foreign('branch_id')->references('id')->on('branches')->cascadeOnDelete();
        });

        foreach (DB::table('roster_slots')->select('branch_id', 'subject')->distinct()->get() as $subject) {
            DB::table('school_subjects')->insert([
                'id' => Uuid::uuid7()->toString(),
                'branch_id' => $subject->branch_id,
                'name' => $subject->subject,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('school_subjects');

        Schema::table('branches', function (Blueprint $table): void {
            $table->dropColumn(['leader_name', 'leader_title', 'phone']);
        });
    }
};
