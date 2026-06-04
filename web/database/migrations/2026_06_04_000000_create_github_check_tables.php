<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('github_check_batches', function (Blueprint $table) {
            $table->id();
            $this->addMatchingForeignColumn($table, 'admin_id', 'users');
            $table->integer('total_accounts')->default(0);
            $table->integer('checked_count')->default(0);
            $table->string('status', 20)->default('running'); // running, completed, stopped
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->foreign('admin_id')->references('id')->on('users')->onDelete('cascade');
        });

        Schema::create('github_check_results', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('batch_id');
            $table->string('username');
            $table->string('result', 20); // approved, not_approved, suspended, error
            $table->text('detail')->nullable();
            $this->addMatchingForeignColumn($table, 'stock_unit_id', 'stock_units')->nullable();
            $table->timestamp('checked_at')->useCurrent();

            $table->foreign('batch_id')->references('id')->on('github_check_batches')->onDelete('cascade');
            $table->foreign('stock_unit_id')->references('id')->on('stock_units')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('github_check_results');
        Schema::dropIfExists('github_check_batches');
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
