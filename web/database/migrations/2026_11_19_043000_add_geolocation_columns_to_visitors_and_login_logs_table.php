<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('visitors')) {
            Schema::table('visitors', function (Blueprint $table) {
                if (!Schema::hasColumn('visitors', 'location')) {
                    $table->string('location', 191)->nullable()->after('visited_date');
                }
                if (!Schema::hasColumn('visitors', 'country')) {
                    $table->string('country', 100)->nullable()->after('location');
                }
                if (!Schema::hasColumn('visitors', 'country_code')) {
                    $table->string('country_code', 10)->nullable()->after('country');
                }
                if (!Schema::hasColumn('visitors', 'region')) {
                    $table->string('region', 100)->nullable()->after('country_code');
                }
                if (!Schema::hasColumn('visitors', 'city')) {
                    $table->string('city', 100)->nullable()->after('region');
                }
                if (!Schema::hasColumn('visitors', 'latitude')) {
                    $table->decimal('latitude', 10, 7)->nullable()->after('city');
                }
                if (!Schema::hasColumn('visitors', 'longitude')) {
                    $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
                }
                if (!Schema::hasColumn('visitors', 'last_path')) {
                    $table->string('last_path', 255)->nullable()->after('longitude');
                }
                if (!Schema::hasColumn('visitors', 'hits_count')) {
                    $table->unsignedInteger('hits_count')->default(1)->after('last_path');
                }
            });
        }

        if (Schema::hasTable('login_logs')) {
            Schema::table('login_logs', function (Blueprint $table) {
                if (!Schema::hasColumn('login_logs', 'country')) {
                    $table->string('country', 100)->nullable()->after('location');
                }
                if (!Schema::hasColumn('login_logs', 'country_code')) {
                    $table->string('country_code', 10)->nullable()->after('country');
                }
                if (!Schema::hasColumn('login_logs', 'region')) {
                    $table->string('region', 100)->nullable()->after('country_code');
                }
                if (!Schema::hasColumn('login_logs', 'city')) {
                    $table->string('city', 100)->nullable()->after('region');
                }
                if (!Schema::hasColumn('login_logs', 'latitude')) {
                    $table->decimal('latitude', 10, 7)->nullable()->after('city');
                }
                if (!Schema::hasColumn('login_logs', 'longitude')) {
                    $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('visitors')) {
            Schema::table('visitors', function (Blueprint $table) {
                $cols = array_values(array_filter(
                    ['location', 'country', 'country_code', 'region', 'city', 'latitude', 'longitude', 'last_path', 'hits_count'],
                    fn ($c) => Schema::hasColumn('visitors', $c)
                ));
                if (!empty($cols)) {
                    $table->dropColumn($cols);
                }
            });
        }

        if (Schema::hasTable('login_logs')) {
            Schema::table('login_logs', function (Blueprint $table) {
                $cols = array_values(array_filter(
                    ['country', 'country_code', 'region', 'city', 'latitude', 'longitude'],
                    fn ($c) => Schema::hasColumn('login_logs', $c)
                ));
                if (!empty($cols)) {
                    $table->dropColumn($cols);
                }
            });
        }
    }
};
