<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organization_settings', function (Blueprint $table): void {
            $table->string('login_panel_badge', 100)->default('Administrasi terpadu');
            $table->string('login_panel_headline', 180)->default('Mengelola pendidikan, dengan lebih terarah.');
            $table->string('login_panel_description', 1000)->default('Satu ruang kerja untuk mengelola sekolah, siswa, layanan, dan operasional yayasan dengan rapi.');
            $table->string('login_panel_image_path')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('organization_settings', function (Blueprint $table): void {
            $table->dropColumn([
                'login_panel_badge',
                'login_panel_headline',
                'login_panel_description',
                'login_panel_image_path',
            ]);
        });
    }
};
