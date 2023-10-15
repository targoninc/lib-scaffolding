<?php

namespace TargonIndustries\Scaffolder;

use Exception;
use mysqli;

require_once 'FieldCasing.php';
require_once 'IScaffolder.php';
require_once 'ScaffoldingLanguage.php';
require_once 'ScaffoldingSettings.php';
require_once 'ClassCreatorPHP.php';
require_once 'ClassCreatorJS.php';

class MySqlScaffolder implements IScaffolder
{
    private ?mysqli $connection;
    public ScaffoldingSettings $settings;

    /**
     * @throws Exception
     */
    public function __construct(string $host = null, string $username = null, string $password = null, string $database = null)
    {
        $host = $host ?? getenv('DB_HOST');
        $username = $username ?? getenv('DB_USER');
        $password = $password ?? getenv('DB_PASS');
        $database = $database ?? getenv('DB_NAME') ?? 'default';
        if (empty($host) || empty($username) || empty($password) || empty($database)) {
            throw new Exception('Missing connection parameters');
        }
        $this->setConnection(new mysqli($host, $username, $password, $database));
        $this->settings = new ScaffoldingSettings();
    }

    function setConnection(mixed $connection): void
    {
        $this->connection = $connection;
    }

    public function getTables(string $database): array
    {
        $tables = array();
        $result = $this->connection->query("SHOW TABLES FROM $database");
        while ($row = $result->fetch_row()) {
            $tables[] = $row[0];
        }
        return $tables;
    }

    public function getFields(string $database, string $table): array
    {
        $fields = array();
        $result = $this->connection->query("SHOW COLUMNS FROM $database.$table");
        while ($row = $result->fetch_assoc()) {
            $row['nullable'] = $row['Null'] === 'YES';
            $fields[] = $row;
        }
        return $fields;
    }

    public function getForeignKeys(string $database, string $table): array
    {
        $foreignKeys = array();
        $result = $this->connection->query("SELECT * FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = '$database' AND TABLE_NAME = '$table' AND REFERENCED_TABLE_NAME IS NOT NULL");
        while ($row = $result->fetch_assoc()) {
            $foreignKeys[] = [
                'type' => $row['REFERENCED_TABLE_NAME'],
                'field' => $row['REFERENCED_TABLE_NAME'],
                'nullable' => true,
            ];
        }
        return $foreignKeys;
    }

    private function findForeignKeysInOtherTables(string $table) : array
    {
        $foreignKeys = array();
        $result = $this->connection->query("SELECT * FROM information_schema.KEY_COLUMN_USAGE WHERE REFERENCED_TABLE_NAME = '$table'");
        while ($row = $result->fetch_assoc()) {
            $foreignKeys[] = [
                'type' => $row['TABLE_NAME'],
                'field' => $this->parseConstraintName($row['CONSTRAINT_NAME']),
                'nullable' => true,
            ];
        }
        return $foreignKeys;
    }

    private function parseConstraintName(string $name): string
    {
        if (!$this->settings->parseConstraints) {
            return $name;
        }
        $outName = str_replace('_fk_', '', $name);
        $outName = str_replace('_fk', '', $outName);
        $outName = str_replace('_id', '', $outName);
        return substr($outName, 0, strrpos($outName, '_'));
    }

    /**
     * @throws Exception
     */
    public function scaffold(ScaffoldingLanguage $language, string $namespace, string $path, string $database): void
    {
        $tables = $this->getTables($database);
        foreach ($tables as $table) {
            $fields = $this->getFields($database, $table);
            $foreignKeys = $this->getForeignKeys($database, $table);
            $inverseForeignKeys = $this->findForeignKeysInOtherTables($table);
            $classCreator = match ($language) {
                ScaffoldingLanguage::PHP => new ClassCreatorPHP($this->settings),
                ScaffoldingLanguage::JS => new ClassCreatorJS($this->settings),
            };
            $classCreator->createClass($namespace, $table, $fields, $foreignKeys, $inverseForeignKeys, $path);
        }
    }
}