<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RestoreArchivedRepairRow extends Command
{
    protected $signature = 'db:restore-archived-row {archiveId : data_repair_archives.id} {--force : Restore without interactive confirmation}';
    protected $description = 'Explicitly restore one archived legacy row after its parent relationship has been repaired.';

    public function handle(): int
    {
        if (!Schema::hasTable('data_repair_archives')) {
            $this->error('data_repair_archives does not exist.');
            return self::FAILURE;
        }

        $archive = DB::table('data_repair_archives')->find((int) $this->argument('archiveId'));
        if (!$archive) {
            $this->error('Archive row not found.');
            return self::FAILURE;
        }
        if (!Schema::hasTable($archive->source_table)) {
            $this->error("Source table {$archive->source_table} does not exist.");
            return self::FAILURE;
        }

        $payload = json_decode($archive->payload, true);
        if (!is_array($payload) || !$payload) {
            $this->error('Archive payload is invalid.');
            return self::FAILURE;
        }

        $columns = Schema::getColumnListing($archive->source_table);
        $payload = array_intersect_key($payload, array_flip($columns));
        if (!$payload) {
            $this->error('No compatible columns remain to restore.');
            return self::FAILURE;
        }

        if (!$this->option('force') && !$this->confirm("Restore archived row #{$archive->id} into {$archive->source_table}?")) {
            return self::SUCCESS;
        }

        DB::transaction(function () use ($archive, $payload) {
            if (isset($payload['id']) && DB::table($archive->source_table)->where('id', $payload['id'])->exists()) {
                throw new \RuntimeException('A row with the same primary key already exists.');
            }
            DB::table($archive->source_table)->insert($payload);
        });

        $this->info('Archived row restored. The archive copy was intentionally kept for auditability.');
        return self::SUCCESS;
    }
}
