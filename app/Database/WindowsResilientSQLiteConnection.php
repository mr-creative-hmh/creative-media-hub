<?php

namespace App\Database;

use Illuminate\Database\SQLiteConnection;

class WindowsResilientSQLiteConnection extends SQLiteConnection
{
    /**
     * Get a schema builder instance for the connection.
     *
     * @return WindowsResilientSQLiteBuilder
     */
    public function getSchemaBuilder()
    {
        if (is_null($this->schemaGrammar)) {
            $this->useDefaultSchemaGrammar();
        }

        return new WindowsResilientSQLiteBuilder($this);
    }
}
