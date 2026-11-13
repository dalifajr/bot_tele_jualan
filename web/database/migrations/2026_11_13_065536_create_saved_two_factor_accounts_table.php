<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Drop table if it was created partially during previous failed migration attempt
        Schema::dropIfExists('saved_two_factor_accounts');

        Schema::create('saved_two_factor_accounts', function (Blueprint $table) {
            $table->id();

            // Match the exact data type of users.id (INT vs BIGINT, signed vs unsigned)
            $this->addMatchingForeignColumn($table, 'user_id', 'users', 'id');

            $table->string('name');
            $table->string('secret');
            $table->string('issuer')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['user_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('saved_two_factor_accounts');
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
