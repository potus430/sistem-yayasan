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
        Schema::create('organization_settings', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('application_name', 120);
            $table->string('foundation_name', 180);
            $table->text('foundation_address')->nullable();
            $table->timestamps();
        });

        $applicationName = (string) config('app.name', 'Administrasi Yayasan');
        if ($applicationName === 'Laravel') {
            $applicationName = 'Administrasi Yayasan';
        }

        DB::table('organization_settings')->insert([
            'id' => Uuid::uuid7()->toString(),
            'application_name' => $applicationName,
            'foundation_name' => 'Yayasan',
            'foundation_address' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_settings');
    }
};
