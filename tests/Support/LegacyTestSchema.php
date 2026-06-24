<?php

namespace Tests\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LegacyTestSchema
{
    public static function configureInMemoryConnection(): void
    {
        config([
            'database.connections.legacy' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
        ]);

        DB::purge('legacy');
    }

    public static function create(): void
    {
        $schema = Schema::connection('legacy');

        $schema->create('play_times', function (Blueprint $table): void {
            $table->integer('id')->primary();
            $table->string('name');
            $table->integer('duration');
            $table->decimal('price', 10, 2);
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });

        $schema->create('bills', function (Blueprint $table): void {
            $table->integer('id')->primary();
            $table->string('bill_number');
            $table->integer('children_count');
            $table->string('phone_number');
            $table->timestamp('entry_time');
            $table->boolean('is_reward')->default(false);
            $table->timestamp('exit_time')->nullable();
            $table->integer('total_duration')->default(0);
            $table->decimal('total_price', 10, 2)->default(0);
            $table->string('status')->default('active');
            $table->boolean('counts_visit')->default(true);
            $table->timestamp('created_at');
            $table->timestamp('updated_at');
            $table->integer('original_duration')->nullable();
        });

        $schema->create('extensions', function (Blueprint $table): void {
            $table->integer('id')->primary();
            $table->integer('bill_id');
            $table->integer('play_time_id');
            $table->integer('duration');
            $table->decimal('price', 10, 2);
            $table->timestamp('created_at')->nullable();
        });

        $schema->create('loyalty_settings', function (Blueprint $table): void {
            $table->integer('id')->primary();
            $table->integer('reward_threshold')->default(5);
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();
        });

        $schema->create('phone_rewards', function (Blueprint $table): void {
            $table->integer('id')->primary();
            $table->string('phone_number');
            $table->integer('children_count');
            $table->integer('play_time_id');
            $table->boolean('is_used')->default(false);
            $table->dateTime('created_at')->nullable();
            $table->dateTime('used_at')->nullable();
        });

        $schema->create('phone_credits', function (Blueprint $table): void {
            $table->integer('id')->primary();
            $table->string('phone_number');
            $table->integer('children_count');
            $table->integer('play_time_id');
            $table->boolean('counts_visit')->default(true);
            $table->boolean('is_used')->default(false);
            $table->dateTime('created_at')->nullable();
            $table->dateTime('used_at')->nullable();
        });

        $schema->create('users', function (Blueprint $table): void {
            $table->integer('id')->primary();
            $table->string('username');
            $table->string('password');
            $table->string('name');
            $table->string('role')->default('user');
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
    }

    public static function seedSampleData(): void
    {
        $legacy = DB::connection('legacy');

        $legacy->table('play_times')->insert([
            ['id' => 1, 'name' => 'ساعة واحدة', 'duration' => 65, 'price' => 50, 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 9, 'name' => 'نصف ساعة', 'duration' => 33, 'price' => 0, 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'name' => 'ساعتين', 'duration' => 125, 'price' => 90, 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $legacy->table('loyalty_settings')->insert([
            'id' => 1,
            'reward_threshold' => 5,
            'created_at' => '2025-01-01 00:00:00',
            'updated_at' => '2025-01-01 00:00:00',
        ]);

        $legacy->table('bills')->insert([
            [
                'id' => 21,
                'bill_number' => '42',
                'children_count' => 1,
                'phone_number' => '0534474054',
                'entry_time' => '2025-07-09 15:56:55',
                'is_reward' => 0,
                'exit_time' => '2025-07-09 16:40:46',
                'total_duration' => 33,
                'total_price' => 0,
                'status' => 'completed',
                'counts_visit' => 1,
                'created_at' => '2025-07-09 15:56:55',
                'updated_at' => '2025-07-09 18:19:41',
                'original_duration' => 33,
            ],
            [
                'id' => 267,
                'bill_number' => 'مكافأة1',
                'children_count' => 2,
                'phone_number' => '0544177795',
                'entry_time' => '2025-07-20 18:42:45',
                'is_reward' => 1,
                'exit_time' => '2025-07-20 19:45:12',
                'total_duration' => 0,
                'total_price' => 0,
                'status' => 'completed',
                'counts_visit' => 1,
                'created_at' => '2025-07-20 15:42:45',
                'updated_at' => '2025-07-20 16:45:12',
                'original_duration' => 65,
            ],
            [
                'id' => 699,
                'bill_number' => 'رصيد1',
                'children_count' => 1,
                'phone_number' => '0566101456',
                'entry_time' => '2025-08-13 16:59:21',
                'is_reward' => 0,
                'exit_time' => '2025-08-13 19:05:49',
                'total_duration' => 0,
                'total_price' => 0,
                'status' => 'completed',
                'counts_visit' => 0,
                'created_at' => '2025-08-13 13:59:21',
                'updated_at' => '2025-08-13 16:05:49',
                'original_duration' => 125,
            ],
        ]);

        $legacy->table('extensions')->insert([
            ['id' => 37, 'bill_id' => 21, 'play_time_id' => 9, 'duration' => 33, 'price' => 0, 'created_at' => '2025-07-09 15:56:55'],
            ['id' => 751, 'bill_id' => 699, 'play_time_id' => 2, 'duration' => 125, 'price' => 90, 'created_at' => '2025-08-13 13:59:21'],
        ]);

        $legacy->table('phone_rewards')->insert([
            [
                'id' => 6,
                'phone_number' => '0544177795',
                'children_count' => 2,
                'play_time_id' => 1,
                'is_used' => 1,
                'created_at' => '2025-07-19 17:26:01',
                'used_at' => '2025-07-20 15:42:45',
            ],
            [
                'id' => 384,
                'phone_number' => '0553172518',
                'children_count' => 2,
                'play_time_id' => 1,
                'is_used' => 0,
                'created_at' => '2026-06-08 19:14:46',
                'used_at' => null,
            ],
            [
                'id' => 320,
                'phone_number' => '0508288384',
                'children_count' => 2,
                'play_time_id' => 1,
                'is_used' => 0,
                'created_at' => '2026-05-02 12:53:59',
                'used_at' => null,
            ],
        ]);

        $legacy->table('phone_credits')->insert([
            [
                'id' => 3,
                'phone_number' => '0566101456',
                'children_count' => 1,
                'play_time_id' => 2,
                'counts_visit' => 0,
                'is_used' => 1,
                'created_at' => '2025-08-12 22:43:29',
                'used_at' => '2025-08-13 13:59:21',
            ],
        ]);

        $legacy->table('users')->insert([
            [
                'id' => 1,
                'username' => 'admin',
                'password' => 'Bb@19881988',
                'name' => 'مدير النظام',
                'role' => 'admin',
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
