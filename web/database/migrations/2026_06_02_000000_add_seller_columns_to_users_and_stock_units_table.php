<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'wallet_balance')) {
                $table->integer('wallet_balance')->default(0)->after('role');
            }
            if (!Schema::hasColumn('users', 'platform_fee_percent')) {
                $table->integer('platform_fee_percent')->default(10)->after('wallet_balance');
            }
            if (!Schema::hasColumn('users', 'seller_save_hours')) {
                $table->integer('seller_save_hours')->default(80)->after('platform_fee_percent');
            }
        });

        Schema::table('stock_units', function (Blueprint $table) {
            if (!Schema::hasColumn('stock_units', 'seller_id')) {
                $this->addMatchingForeignColumn($table, 'seller_id', 'users')->nullable()->after('product_id');
                $table->foreign('seller_id')->references('id')->on('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('stock_units', 'uploaded_by_id')) {
                $this->addMatchingForeignColumn($table, 'uploaded_by_id', 'users')->nullable()->after('seller_id');
                $table->foreign('uploaded_by_id')->references('id')->on('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['wallet_balance', 'platform_fee_percent', 'seller_save_hours']);
        });

        Schema::table('stock_units', function (Blueprint $table) {
            if (Schema::hasColumn('stock_units', 'seller_id')) {
                $table->dropForeign(['seller_id']);
                $table->dropColumn('seller_id');
            }
            if (Schema::hasColumn('stock_units', 'uploaded_by_id')) {
                $table->dropForeign(['uploaded_by_id']);
                $table->dropColumn('uploaded_by_id');
            }
        });
    }

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
                $dataType = strtolower($row->DATA_TYPE);
                $columnType = strtolower($row->COLUMN_TYPE);

                $isBigInt = str_contains($dataType, 'bigint');
                $isUnsigned = str_contains($columnType, 'unsigned');
            }
        } elseif ($driver === 'sqlite') {
            $rows = DB::select("PRAGMA table_info({$referencedTable})");
            foreach ($rows as $row) {
                if ($row->name === $referencedColumn) {
                    $type = strtolower($row->type);
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

