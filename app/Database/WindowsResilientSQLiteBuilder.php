<?php

namespace App\Database;

use Illuminate\Database\Schema\SQLiteBuilder;

class WindowsResilientSQLiteBuilder extends SQLiteBuilder
{
    /**
     * Drop all tables from the database using pure SQL instead of truncating locked file.
     * Prevents Windows 'Permission denied' file-lock crashes with SQLite WAL mode.
     *
     * @return void
     */
    public function dropAllTables()
    {
        $this->connection->statement($this->grammar->compileDisableForeignKeyConstraints());

        $tables = $this->connection->select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");
        foreach ($tables as $table) {
            $tableName = is_object($table) ? ($table->name ?? reset($table)) : (is_array($table) ? ($table['name'] ?? reset($table)) : $table);
            $this->connection->statement('DROP TABLE IF EXISTS "' . $tableName . '"');
        }

        $this->connection->statement($this->grammar->compileEnableForeignKeyConstraints());
    }

    /**
     * Drop all views from the database.
     *
     * @return void
     */
    public function dropAllViews()
    {
        $views = $this->connection->select("SELECT name FROM sqlite_master WHERE type='view'");
        foreach ($views as $view) {
            $viewName = is_object($view) ? ($view->name ?? reset($view)) : (is_array($view) ? ($view['name'] ?? reset($view)) : $view);
            $this->connection->statement('DROP VIEW IF EXISTS "' . $viewName . '"');
        }
    }
}
