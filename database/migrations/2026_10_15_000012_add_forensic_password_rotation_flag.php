<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('must_change_password')->default(false)->after('is_active');
        });

        $forensicEmail = env('SUPER_ADMIN_EMAIL');
        if ($forensicEmail) {
            DB::table('users')
                ->where('role', 'super_admin')
                ->whereRaw('LOWER(email) = ?', [strtolower(trim($forensicEmail))])
                ->update(['must_change_password' => true]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('must_change_password');
        });
    }
};
