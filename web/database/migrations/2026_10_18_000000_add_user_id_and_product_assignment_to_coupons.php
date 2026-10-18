<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Add user_id to coupons table
        if (Schema::hasTable('coupons') && !Schema::hasColumn('coupons', 'user_id')) {
            Schema::table('coupons', function (Blueprint $table) {
                $this->addMatchingForeignColumn($table, 'user_id', 'users')->nullable()->after('id')->index();
            });
        }

        // 2. Create coupon_product pivot table
        // Drop partial table if previous run failed midway
        if (Schema::hasTable('coupon_product')) {
            Schema::dropIfExists('coupon_product');
        }

        Schema::create('coupon_product', function (Blueprint $table) {
            $table->id();
            $this->addMatchingForeignColumn($table, 'coupon_id', 'coupons')->index();
            $this->addMatchingForeignColumn($table, 'product_id', 'products')->index();
            $table->timestamps();

            $table->unique(['coupon_id', 'product_id']);
        });

        // Add foreign keys in separate try-catch blocks for maximum cross-database compatibility (MySQL / SQLite)
        try {
            Schema::table('coupon_product', function (Blueprint $table) {
                $table->foreign('coupon_id')->references('id')->on('coupons')->onDelete('cascade');
            });
        } catch (\Throwable $e) {
            Log::warning("Could not add foreign key coupon_product_coupon_id: " . $e->getMessage());
        }

        try {
            Schema::table('coupon_product', function (Blueprint $table) {
                $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            });
        } catch (\Throwable $e) {
            Log::warning("Could not add foreign key coupon_product_product_id: " . $e->getMessage());
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coupon_product');

        if (Schema::hasTable('coupons') && Schema::hasColumn('coupons', 'user_id')) {
            Schema::table('coupons', function (Blueprint $table) {
                $table->dropColumn('user_id');
            });
        }
    }

    /**
     * Helper to add a column with matching data type and signedness of referenced table's column.
     */
    private function addMatchingForeignColumn(Blueprint $table, string $columnName, string $referencedTable, string $referencedColumn = 'id')
    {
        $connection = Schema::getConnection();
        $driver = $connection->getDriverName();

        $isBigInt = true;
        $isUnsigned = true;

        if ($driver === 'mysql') {
            $database = $connection->getDatabaseName();
            $row = DB::selectOne(
                "SELECT DATA_TYPE, COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS 
                 WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?",
                [$database, $referencedTable, $referencedColumn]
            );

            if ($row) {
                $rowArray = (array) $row;
                $dataType = strtolower($rowArray['DATA_TYPE'] ?? $rowArray['data_type'] ?? '');
                $columnType = strtolower($rowArray['COLUMN_TYPE'] ?? $rowArray['column_type'] ?? '');

                $isBigInt = str_contains($dataType, 'bigint');
                $isUnsigned = str_contains($columnType, 'unsigned');
            }
        } elseif ($driver === 'sqlite') {
            $rows = DB::select("PRAGMA table_info({$referencedTable})");
            foreach ($rows as $row) {
                $rowObj = (object) $row;
                if ($rowObj->name === $referencedColumn) {
                    $type = strtolower($rowObj->type ?? '');
                    $isBigInt = str_contains($type, 'bigint') || str_contains($type, 'integer') || $type === '';
                    $isUnsigned = str_contains($type, 'unsigned');
                    break;
                }
            }
        }

        if ($isBigInt) {
            return $isUnsigned 
                ? $table->unsignedBigInteger($columnName) 
                : $table->bigInteger($columnName);
        } else {
            return $isUnsigned 
                ? $table->unsignedInteger($columnName) 
                : $table->integer($columnName);
        }
    }
};
