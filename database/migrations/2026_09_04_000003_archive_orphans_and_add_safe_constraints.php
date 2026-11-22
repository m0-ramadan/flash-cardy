<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('data_repair_archives')) {
            return;
        }

        // Archive invalid relationship rows before deleting them. This keeps the migration
        // deterministic while guaranteeing that no legacy data is irreversibly lost.
        $this->archiveAndDeleteOrphans('cart_items', 'product_id', 'products');
        $this->archiveAndDeleteOrphans('favourites', 'product_id', 'products');
        $this->archiveAndDeleteOrphans('favourites', 'user_id', 'users');
        $this->archiveAndDeleteOrphans('reviews', 'product_id', 'products');
        $this->archiveAndDeleteOrphans('reviews', 'user_id', 'users');
        $this->archiveAndDeleteOrphans('features', 'product_id', 'products');
        $this->archiveAndDeleteOrphans('offer_product', 'product_id', 'products');
        $this->archiveAndDeleteOrphans('offer_product', 'offer_id', 'offers');

        $this->archiveAndDeleteDuplicates('favourites', ['user_id', 'product_id']);
        $this->archiveAndDeleteDuplicates('reviews', ['user_id', 'product_id']);
        $this->archiveAndDeleteDuplicates('offer_product', ['offer_id', 'product_id']);

        $this->addUniqueIndexIfMissing('favourites', ['user_id', 'product_id'], 'favourites_user_product_unique');
        $this->addUniqueIndexIfMissing('reviews', ['user_id', 'product_id'], 'reviews_user_product_unique');
        $this->addUniqueIndexIfMissing('offer_product', ['offer_id', 'product_id'], 'offer_product_unique');
        $this->addUniqueIndexIfMissing('cart_items', ['cart_id', 'hash_key'], 'cart_items_cart_hash_unique');

        // Cascades are used only for ephemeral/relation data where retaining a dangling row has no value.
        $this->addForeignKeyIfMissing('cart_items', 'cart_id', 'carts', 'id', 'cascade');
        $this->addForeignKeyIfMissing('cart_items', 'product_id', 'products', 'id', 'cascade');
        $this->addForeignKeyIfMissing('favourites', 'user_id', 'users', 'id', 'cascade');
        $this->addForeignKeyIfMissing('favourites', 'product_id', 'products', 'id', 'cascade');
        $this->addForeignKeyIfMissing('reviews', 'user_id', 'users', 'id', 'cascade');
        $this->addForeignKeyIfMissing('reviews', 'product_id', 'products', 'id', 'cascade');
        $this->addForeignKeyIfMissing('features', 'product_id', 'products', 'id', 'cascade');
        $this->addForeignKeyIfMissing('offer_product', 'offer_id', 'offers', 'id', 'cascade');
        $this->addForeignKeyIfMissing('offer_product', 'product_id', 'products', 'id', 'cascade');
    }

    private function archiveAndDeleteOrphans(string $child, string $foreignColumn, string $parent, string $parentColumn = 'id'): void
    {
        if (!Schema::hasTable($child) || !Schema::hasTable($parent) || !Schema::hasColumn($child, $foreignColumn)) {
            return;
        }

        DB::table($child)
            ->leftJoin($parent, "{$child}.{$foreignColumn}", '=', "{$parent}.{$parentColumn}")
            ->whereNotNull("{$child}.{$foreignColumn}")
            ->whereNull("{$parent}.{$parentColumn}")
            ->select("{$child}.*")
            ->orderBy("{$child}.id")
            ->chunkById(250, function ($rows) use ($child, $foreignColumn, $parent) {
                foreach ($rows as $row) {
                    $payload = (array) $row;
                    $id = $payload['id'] ?? null;
                    $this->archive($child, $id !== null ? (string) $id : null, "orphan {$foreignColumn} -> {$parent}", $payload);
                    if ($id !== null) {
                        DB::table($child)->where('id', $id)->delete();
                    }
                }
            }, "{$child}.id", 'id');
    }

    private function archiveAndDeleteDuplicates(string $table, array $columns): void
    {
        if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'id')) {
            return;
        }

        $query = DB::table($table)
            ->select(array_merge($columns, [DB::raw('MIN(id) as keep_id'), DB::raw('COUNT(*) as duplicate_count')]))
            ->groupBy($columns)
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($query as $group) {
            $duplicates = DB::table($table);
            foreach ($columns as $column) {
                $duplicates->where($column, $group->{$column});
            }
            $rows = $duplicates->where('id', '<>', $group->keep_id)->get();
            foreach ($rows as $row) {
                $payload = (array) $row;
                $this->archive($table, (string) $row->id, 'duplicate ' . implode('+', $columns), $payload);
                DB::table($table)->where('id', $row->id)->delete();
            }
        }
    }

    private function archive(string $table, ?string $key, string $reason, array $payload): void
    {
        DB::table('data_repair_archives')->insert([
            'source_table' => $table,
            'source_key' => $key,
            'reason' => $reason,
            'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'archived_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function addUniqueIndexIfMissing(string $table, array $columns, string $name): void
    {
        if (!Schema::hasTable($table) || $this->indexExists($table, $name)) {
            return;
        }
        try {
            Schema::table($table, function (Blueprint $blueprint) use ($columns, $name) {
                $blueprint->unique($columns, $name);
            });
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function addForeignKeyIfMissing(string $table, string $column, string $parent, string $parentColumn, string $onDelete): void
    {
        if (!Schema::hasTable($table) || !Schema::hasTable($parent) || !Schema::hasColumn($table, $column)) {
            return;
        }
        if ($this->foreignKeyExists($table, $column)) {
            return;
        }

        try {
            Schema::table($table, function (Blueprint $blueprint) use ($column, $parent, $parentColumn, $onDelete) {
                $fk = $blueprint->foreign($column)->references($parentColumn)->on($parent);
                if ($onDelete === 'cascade') {
                    $fk->cascadeOnDelete();
                } elseif ($onDelete === 'set null') {
                    $fk->nullOnDelete();
                } else {
                    $fk->restrictOnDelete();
                }
            });
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function indexExists(string $table, string $indexName): bool
    {
        if (DB::getDriverName() !== 'mysql') {
            return false;
        }
        return DB::table('information_schema.statistics')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', $table)
            ->where('index_name', $indexName)
            ->exists();
    }

    private function foreignKeyExists(string $table, string $column): bool
    {
        if (DB::getDriverName() !== 'mysql') {
            return false;
        }
        return DB::table('information_schema.key_column_usage')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', $table)
            ->where('column_name', $column)
            ->whereNotNull('referenced_table_name')
            ->exists();
    }

    public function down(): void
    {
        // Data repair archives and constraints remain intentionally. Rolling them back could re-create
        // invalid relations. Archived rows can be restored explicitly after fixing their parent records.
    }
};
