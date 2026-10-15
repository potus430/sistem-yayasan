<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE branches MODIFY level ENUM('TK', 'SD', 'SMP', 'SMA', 'YAYASAN') NOT NULL");

        if (! DB::table('branches')->where('level', 'YAYASAN')->exists()) {
            DB::table('branches')->insert([
                'id' => (string) Str::uuid(),
                'name' => 'Yayasan Pusat',
                'level' => 'YAYASAN',
                'address' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        $headOfficeId = DB::table('branches')->where('level', 'YAYASAN')->value('id');
        if ($headOfficeId) {
            $hasData = collect([
                'employees',
                'users',
                'students',
                'salaries',
                'attendances',
                'roster_slots',
                'annual_incentive_assignments',
                'employee_position_assignments',
                'branch_positions',
                'branch_incentives',
                'spp_billings',
            ])->contains(fn (string $table): bool => Schema::hasTable($table)
                && DB::table($table)->where('branch_id', $headOfficeId)->exists());

            if ($hasData) {
                throw new RuntimeException('Cannot remove the Yayasan Pusat payroll unit while it still has employees or users.');
            }

            DB::table('branches')->where('id', $headOfficeId)->delete();
        }

        DB::statement("ALTER TABLE branches MODIFY level ENUM('TK', 'SD', 'SMP', 'SMA') NOT NULL");
    }
};
