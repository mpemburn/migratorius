<?php

namespace App\Services;

use App\Models\DynamicModel;
use App\Models\DbMigration;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MigrateTablesService
{
    protected array $databases;
    protected int $sourceBlogId;
    protected string $sourceDatabase;
    protected string $destDatabase;
    protected int $destBlogId;
    protected string $prefix = 'wp_';
    protected string $destBlogUrl;
    protected string $datetimePrefix;
    protected string $migrationsPath;
    protected Collection $blogTables;
    protected Collection $createTableStatements;
    protected Collection $dropTableStatements;
    protected Collection $inserts;
    protected DbMigration $migrationRecord;

    public function __construct()
    {
        $this->databases = DatabaseService::getInverseDatabaseList();
        $this->blogTables = collect();
        $this->createTableStatements = collect();
        $this->dropTableStatements = collect();
        $this->inserts = collect();
        $this->migrationsPath = base_path() . '/database/migrations';
        $this->datetimePrefix = Carbon::now()->format('Y_m_d_His');
    }

    public function migrateMultiple(string $sourceDb, string $destDb, array $blogIds): bool
    {
        foreach ($blogIds as $blogId) {
            $this->setBlogToMigrate($blogId)
                ->setSourceDatabase($sourceDb)
                ->setDestDatabase($destDb)
                ->run();
        }

        return true;
    }

    public function setBlogToMigrate(int $blogId): self
    {
        $this->sourceBlogId = $blogId;

        return $this;
    }

    public function setSourceDatabase(string $sourceDatabase): self
    {
        $this->sourceDatabase = $sourceDatabase;

        return $this;
    }

    public function setDestDatabase(string $destDatabase): self
    {
        $this->destDatabase = $destDatabase;

        return $this;
    }

    protected function switchToDatabase(string $databaseName)
    {
        DatabaseService::setDb($databaseName);
    }

    protected function createRecord(): void
    {
        $service = new SubsiteService();
        $sourceSiteurl = $service->getBlogUrlById($this->sourceDatabase, $this->sourceBlogId);
        $siteurl = $service->swapUrl($sourceSiteurl, $this->sourceDatabase, $this->destDatabase);

        $this->switchToDatabase(env('DB_DATABASE'));
        $this->migrationRecord = DbMigration::create([
            'sourceDatabase' => $this->sourceDatabase,
            'destDatabase' => $this->destDatabase,
            'subsiteUrl' => $siteurl,
            'sourceSubsiteId' => $this->sourceBlogId,
            'destSubsiteId' => $this->destBlogId,
        ]);
    }
    public function run(): bool
    {
        $this->setDestBlogInfo();
        $this->createRecord();

        $this->switchToDatabase($this->sourceDatabase);

        $this->buildTables($this->sourceDatabase, $this->sourceBlogId);

        return $this->migrate();
    }

    public function migrate(): bool
    {
        $this->blogTables->each(function ($tableName) {
            $this->buildCreateStatements($tableName)
                ->buildInsertRows($tableName);
        });

        // Drop all tables that match the destination ID
        // as well as the wp_blogs entry
        $this->dropTables()
            ->removeBlogsTableEntry();

        $this->createTables()
            ->insertData()
            ->insertBlogRecord();

        $this->switchToDatabase(env('DB_DATABASE'));
        return $this->migrationRecord->update(['created' => true]);
    }

    public function remove(string $dbName, string $blogId)
    {
        $this->switchToDatabase($dbName);

        $this->destBlogId = $blogId;
        $this->setDestDatabase($dbName)
            ->buildTables($dbName, $blogId)
            ->dropTables()
            ->removeBlogsTableEntry();

        $this->switchToDatabase(env('DB_DATABASE'));
        $dbMigration = DbMigration::where('destDatabase', $dbName)
            ->where('destSubsiteId', $blogId)
            ->first();
        $dbMigration->update(['created' => 0]);

    }

    public function buildTables(string $dbName, string $blogId): self
    {
        $tables = DB::select('SHOW TABLES');

        collect($tables)->each(function ($table) use ($blogId, $dbName) {
            $prop = 'Tables_in_' . $dbName;
            if (stripos($table->$prop, $this->prefix . $blogId . '_') !== false) {
                $tableName = $table->$prop;
                // Add table names to collection
                $this->blogTables->push($tableName);
                // Create DROP statements for rollback
                $dropStatement = "DROP TABLE IF EXISTS {$tableName};";
                $this->dropTableStatements->push([$tableName => $dropStatement]);
            }
        });

        return $this;
    }

    protected function setDestBlogInfo()
    {
        $this->switchToDatabase($this->destDatabase);

        // Get the current highest blog ID from the destination
        $blogs = DB::select('SELECT domain, MAX(blog_id) AS max FROM wp_blogs GROUP BY domain');
        $this->destBlogId = (int) current($blogs)->max + 1;
        $this->destBlogUrl = current($blogs)->domain;
    }

    protected function getDestTableName(string $tableName): string
    {
        return str_replace("_{$this->sourceBlogId}_", "_{$this->destBlogId}_", $tableName);
    }

    protected function createTables(): self
    {
        $this->switchToDatabase($this->destDatabase);

        $this->createTableStatements->each(function ($statement) {
            $sql = current($statement);
            DB::statement($sql);
        });

        return $this;
    }

    protected function dropTables(): self
    {
        $this->switchToDatabase($this->destDatabase);

        $this->dropTableStatements->each(function ($statement) {
            $sql = current($statement);
            DB::statement($sql);
        });

        return $this;
    }

    protected function removeBlogsTableEntry(): self
    {
        $this->switchToDatabase($this->destDatabase);

        $blogsTable = $this->prefix . 'blogs';

        DB::statement("DELETE FROM {$blogsTable} WHERE blog_id = {$this->destBlogId}");

        return $this;
    }

    protected function insertData(): self
    {
        // Set sql_mode to prevent error when inserting a record with a "zero" date
        DB::statement("SET sql_mode='ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");

        // Execute prepared statements
        $this->inserts->each(function ($item) {
            DB::insert($item['insert'], $item['values']);
        });

        return $this;
    }

    protected function buildCreateStatements(string $tableName): self
    {
        $destTableName = $this->getDestTableName($tableName);
        $query = DB::select("SHOW CREATE TABLE {$tableName};");
        $prop = 'Create Table';
        $createText = current($query)->$prop;

        $createStatement = str_replace(
            [
                'CREATE TABLE',
                "'0000-00-00 00:00:00'",
                $this->prefix . $this->sourceBlogId
            ],
            [
                'CREATE TABLE IF NOT EXISTS',
                'CURRENT_TIMESTAMP',
                $this->prefix . $this->destBlogId
            ],
            $createText
        );

        $this->createTableStatements->push([$destTableName => $createStatement]);

        return $this;
    }

    protected function buildInsertRows(string $tableName): self
    {
        $insertStub = null;
        $model = new DynamicModel();

        $model->setTable($tableName);

        $rows = $model->select()->get()->toArray();

        $destTableName = $this->getDestTableName($tableName);

        collect($rows)->each(function ($row) use ($destTableName, $insertStub) {
            if (!$insertStub) {
                $insertStub = $this->buildInsertStatement($row, $destTableName);
            }
            // Save insert data
            $this->inserts->push([
                'table' => $destTableName,
                'insert' => $insertStub,
                'values' => $this->swapUrlNames(array_values($row))
            ]);
        });

        return $this;
    }

    protected function swapUrlNames(array $values): array
    {
        return collect($values)->map(function ($value) {
            if (stripos($value, $this->databases[$this->sourceDatabase]) !== false) {
                return str_replace(
                    $this->databases[$this->sourceDatabase],
                    $this->databases[$this->destDatabase],
                    $value
                );
            }

            return $value;
        })->toArray();
    }

    protected function insertBlogRecord(): void
    {
        $this->switchToDatabase($this->sourceDatabase);

        $tableName = $this->prefix . 'blogs';
        $model = new DynamicModel();
        $model->setTable($tableName);

        $blogRecord = $model->where('blog_id', $this->sourceBlogId)->first();
        $blogRecord->blog_id = $this->destBlogId;
        $blogRecord->domain = $this->destBlogUrl;

        $record = $blogRecord->toArray();
        $values = array_values($record);

        $insertStub = $this->buildInsertStatement($record, $tableName);

        $this->switchToDatabase($this->destDatabase);
        DB::insert($insertStub, $values);

    }

    protected function buildInsertStatement($data, string $tableName): string
    {
        $columns = collect(array_keys($data))->map(function ($column) {
            return '`' . $column . '`';
        })->implode(',');
        $placeholders = implode(',', array_fill(0, count(array_keys($data)), '?'));

        $insertStub = "INSERT INTO {$tableName} ({$columns}) VALUES($placeholders);";

        return $insertStub;
    }

}
