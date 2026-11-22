<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Update users table with PIN auth and parental controls
        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'password')) {
                $table->string('password')->nullable()->change();
            }
            if (! Schema::hasColumn('users', 'username')) {
                $table->string('username')->nullable()->unique();
            }
            if (! Schema::hasColumn('users', 'pin')) {
                $table->string('pin', 60)->nullable();
            }
            if (! Schema::hasColumn('users', 'pin_length')) {
                $table->unsignedTinyInteger('pin_length')->default(4);
            }
            if (! Schema::hasColumn('users', 'role')) {
                $table->string('role', 20)->default('user');
            }
            if (! Schema::hasColumn('users', 'avatar')) {
                $table->string('avatar', 50)->default('popcorn');
            }
            if (! Schema::hasColumn('users', 'is_kids')) {
                $table->boolean('is_kids')->default(false);
            }
            if (! Schema::hasColumn('users', 'max_content_rating')) {
                $table->string('max_content_rating', 16)->default('ALL');
            }
            if (! Schema::hasColumn('users', 'blocked_genres')) {
                $table->text('blocked_genres')->nullable();
            }
            if (! Schema::hasColumn('users', 'is_active')) {
                $table->boolean('is_active')->default(true);
            }
        });

        // 2. Ensure default Administrator user exists
        if (DB::table('users')->count() === 0) {
            DB::table('users')->insert([
                'id' => 1,
                'name' => 'Administrator',
                'username' => 'admin',
                'email' => 'admin@creativemedia.hub',
                'password' => Hash::make(Str::random(32)),
                'pin' => Hash::make('1234'),
                'pin_length' => 4,
                'role' => 'admin',
                'avatar' => 'director',
                'is_kids' => false,
                'max_content_rating' => 'ALL',
                'blocked_genres' => null,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            // Ensure at least one admin exists
            $adminExists = DB::table('users')->where('role', 'admin')->exists();
            if (! $adminExists) {
                $firstId = DB::table('users')->min('id');
                DB::table('users')->where('id', $firstId)->update([
                    'role' => 'admin',
                    'username' => 'admin',
                    'pin' => Hash::make('1234'),
                    'pin_length' => 4,
                    'is_active' => true,
                ]);
            }
        }

        // 3. Add content_rating to media_items
        Schema::table('media_items', function (Blueprint $table): void {
            if (! Schema::hasColumn('media_items', 'content_rating')) {
                $table->string('content_rating', 16)->nullable()->index();
            }
        });

        // 4. Add content_rating to series
        Schema::table('series', function (Blueprint $table): void {
            if (! Schema::hasColumn('series', 'content_rating')) {
                $table->string('content_rating', 16)->nullable()->index();
            }
        });

        // 5. Create user_favorites table
        if (! Schema::hasTable('user_favorites')) {
            Schema::create('user_favorites', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('user_id')->index();
                $table->string('favoritable_type');
                $table->unsignedBigInteger('favoritable_id');
                $table->timestamps();

                $table->unique(['user_id', 'favoritable_type', 'favoritable_id'], 'user_fav_unique');
                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            });
        }

        // 6. Update watch_histories unique key to include user_id
        $firstUserId = DB::table('users')->value('id') ?? 1;
        DB::table('watch_histories')->whereNull('user_id')->update(['user_id' => $firstUserId]);

        try {
            Schema::table('watch_histories', function (Blueprint $table): void {
                $table->dropUnique('watch_histories_watchable_unique');
            });
        } catch (Throwable) {
            // Index might not exist or already dropped
        }

        try {
            Schema::table('watch_histories', function (Blueprint $table): void {
                $table->unique(['user_id', 'watchable_type', 'watchable_id'], 'watch_histories_user_watchable_unique');
            });
        } catch (Throwable) {
            // Index might already exist
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_favorites');

        Schema::table('series', function (Blueprint $table): void {
            if (Schema::hasColumn('series', 'content_rating')) {
                $table->dropColumn('content_rating');
            }
        });

        Schema::table('media_items', function (Blueprint $table): void {
            if (Schema::hasColumn('media_items', 'content_rating')) {
                $table->dropColumn('content_rating');
            }
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn([
                'username',
                'pin',
                'pin_length',
                'role',
                'avatar',
                'is_kids',
                'max_content_rating',
                'blocked_genres',
                'is_active',
            ]);
        });
    }
};
