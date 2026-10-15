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
        if (! Schema::hasTable('users')) {
            return;
        }

        if (! Schema::hasColumn('users', 'branch_id')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->uuid('branch_id')->nullable()->index();
            });
        }

        if (! Schema::hasColumn('users', 'role')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->string('role', 32)->default('guru')->index();
            });
        }

        $idColumn = DB::selectOne(
            'SELECT DATA_TYPE AS data_type, CHARACTER_MAXIMUM_LENGTH AS character_length
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [DB::connection()->getDatabaseName(), 'users', 'id']
        );

        if (! $idColumn) {
            throw new RuntimeException('The users.id column could not be inspected.');
        }

        $isUuid = strtolower((string) $idColumn->data_type) === 'uuid'
            || (strtolower((string) $idColumn->data_type) === 'char' && (int) $idColumn->character_length === 36);

        if ($isUuid) {
            return;
        }

        if (! Schema::hasTable('sessions')) {
            throw new RuntimeException('The sessions table is required to migrate user identifiers safely.');
        }

        DB::table('sessions')->delete();

        if (! Schema::hasColumn('users', 'id_uuid')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->uuid('id_uuid')->nullable();
            });
        }

        foreach (DB::table('users')->orderBy('id')->pluck('id') as $legacyId) {
            DB::table('users')
                ->where('id', $legacyId)
                ->update(['id_uuid' => Uuid::uuid7()->toString()]);
        }

        if (Schema::hasColumn('sessions', 'user_id')) {
            $sessionUserId = DB::selectOne(
                'SELECT DATA_TYPE AS data_type, CHARACTER_MAXIMUM_LENGTH AS character_length
                 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?',
                [DB::connection()->getDatabaseName(), 'sessions', 'user_id']
            );

            $sessionHasUuid = $sessionUserId
                && (strtolower((string) $sessionUserId->data_type) === 'uuid'
                    || (strtolower((string) $sessionUserId->data_type) === 'char' && (int) $sessionUserId->character_length === 36));

            if (! $sessionHasUuid) {
                Schema::table('sessions', function (Blueprint $table): void {
                    $table->dropIndex(['user_id']);
                    $table->dropColumn('user_id');
                });

                Schema::table('sessions', function (Blueprint $table): void {
                    $table->uuid('user_id')->nullable()->index();
                });
            }
        } else {
            Schema::table('sessions', function (Blueprint $table): void {
                $table->uuid('user_id')->nullable()->index();
            });
        }

        DB::statement('ALTER TABLE `users` MODIFY `id` BIGINT UNSIGNED NOT NULL');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropPrimary();
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('id');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->renameColumn('id_uuid', 'id');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->primary('id');
        });
    }

    public function down(): void
    {
        throw new RuntimeException('User UUID v7 migration is irreversible; existing sessions and identifiers cannot be safely restored.');
    }
};
