<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('withdrawal_requests')) {
            Schema::create('withdrawal_requests', function (Blueprint $table) {
                $table->id();
                $this->addMatchingForeignColumn($table, 'seller_id', 'users');
                $table->integer('amount');
                $table->string('bank_name', 100);
                $table->string('account_number', 100);
                $table->string('account_holder', 255);
                $table->string('status', 32)->default('pending'); // pending, approved, rejected
                $table->text('rejection_reason')->nullable();
                $table->string('proof_image_path', 255)->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('processed_at')->nullable();

                $table->foreign('seller_id')->references('id')->on('users')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('withdrawal_requests');
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

