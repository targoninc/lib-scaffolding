<?php

namespace TargonIndustries\Scaffolder;

use Exception;
use mysqli;

require_once 'FieldCasing.php';
require_once 'IScaffolder.php';

class MySqlScaffolder implements IScaffolder
{
    private ?mysqli $connection;
    private FieldCasing $fieldCasing;
    private FieldCasing $classCasing;
    private bool $saveAfterCreate;
    private bool $removePlural;
    private bool $parseConstraints;
    private bool $includeRequires;
    private array $nativeImports = [
        'int',
        'string',
        'bool',
        'float',
        'array',
        'DateTime',
        'Exception',
    ];
    private array $nativeClasses = [
        'DateTime',
        'Exception',
    ];
    private bool $useSameNamespace = false;

    public function saveAfterCreate(bool $save): void
    {
        $this->saveAfterCreate = $save;
    }

    public function setFieldCasing(FieldCasing $casing): void
    {
        $this->fieldCasing = $casing;
    }

    public function setClassCasing(FieldCasing $casing): void
    {
        $this->classCasing = $casing;
    }

    public function removePlural(bool $remove): void
    {
        $this->removePlural = $remove;
    }

    public function parseConstraints(bool $parse): void
    {
        $this->parseConstraints = $parse;
    }

    public function includeRequires(bool $include): void
    {
        $this->includeRequires = $include;
    }

    public function useSameNamespace(bool $use): void
    {
        $this->useSameNamespace = $use;
    }

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
        $this->setFieldCasing(FieldCasing::CamelCase);
        $this->setClassCasing(FieldCasing::PascalCase);
        $this->saveAfterCreate(true);
        $this->removePlural(true);
        $this->parseConstraints(true);
        $this->includeRequires(true);
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
                'table' => $row['REFERENCED_TABLE_NAME'],
                'field' => $row['REFERENCED_TABLE_NAME']
            ];
        }
        return $foreignKeys;
    }

    /**
     * @throws Exception if the type is not recognized
     */
    public function mapTypes(array $fields): array
    {
        $fieldsNew = array();
        foreach ($fields as $field) {
            $field['Type'] = $this->mapType($field['Type']);
            $fieldsNew[] = $field;
        }
        return $fieldsNew;
    }

    /**
     * @throws Exception if the type is not recognized
     */
    public function mapType(string $type): array
    {
        $baseType = explode('(', $type)[0];
        $outType = match ($baseType) {
            'int', 'bigint' => 'int',
            'datetime' => 'DateTime',
            'tinyint', 'bit' => 'bool',
            'decimal', 'double' => 'float',
            'varchar', 'longtext', 'text', 'timestamp', 'date' => 'string',
            default => throw new Exception('Unexpected value: ' . $baseType),
        };

        if ($baseType === 'varchar') {
            $comment = 'max length: ' . explode('(', $type)[1];
            $comment = rtrim($comment, ')');
        } else if ($baseType === 'decimal') {
            $comment = 'precision: ' . explode(',', $type)[1];
            $comment = rtrim($comment, ')');
        }

        return [
            'type' => $outType,
            'comment' => $comment ?? '',
        ];
    }

    /**
     * @throws Exception if any of the field types are not recognized
     */
    public function createClass(string $namespace, array $imports, string $name, array $fields, array $foreignKeys, array $inverseForeignKeys, string $path): string
    {
        $class = "<?php\n\nnamespace $namespace;\n\n";

        $fields = $this->mapTypes($fields);
        foreach ($fields as $field) {
            $imports[] = $field['Type']['type'];
        }

        $className = $this->getClassName($name);
        $class .= $this->createImports($imports);
        $class .= "\n\nclass $className\n{\n";
        $class .= $this->createFields($fields);
        $class .= $this->createForeignKeyFields($foreignKeys);
        $class .= $this->createForeignKeyFields($inverseForeignKeys, true);
        $class .= "}\n";
        $class = implode("\n", array_unique(explode("\n", $class)));
        if ($this->saveAfterCreate) {
            $this->saveClass($path . '/' . $className . '.php', $class);
        }
        return $class;
    }

    private function getClassName(string $name): string
    {
        $className = $this->classCasing->convert($name);
        if ($this->removePlural) {
            $className = rtrim($className, 's');
        }
        return $className;
    }

    private function createImports(array $imports): string
    {
        $output = '';
        foreach ($imports as $import) {
            if ($this->useSameNamespace) {
                $output .= "use $import;\n";
            } else {
                $className = explode('\\', $import)[count(explode('\\', $import)) - 1];
                if (!in_array($className, $this->nativeClasses)) {
                    continue;
                }
                $output .= "use $import;\n";
            }
        }
        $output .= "\n";
        if ($this->includeRequires) {
            $requireBase = "require_once __DIR__ . '/";
            foreach ($imports as $import) {
                $className = explode('\\', $import)[count(explode('\\', $import)) - 1];
                if (in_array($className, $this->nativeImports)) {
                    continue;
                }
                $output .= $requireBase . $className . ".php';\n";
            }
        }
        return $output;
    }

    public function saveClass(string $fullPath, string $content): void
    {
        $folder = dirname($fullPath);
        if (!file_exists($folder)) {
            mkdir($folder, 0777, true);
        }
        file_put_contents($fullPath, $content);
    }

    /**
     * @throws Exception
     */
    public function scaffold(string $namespace, string $path, string $database): void
    {
        $tables = $this->getTables($database);
        foreach ($tables as $table) {
            $fields = $this->getFields($database, $table);
            $foreignKeys = $this->getForeignKeys($database, $table);
            $imports = array();
            foreach ($foreignKeys as $foreignKey) {
                $className = $this->getClassName($foreignKey['table']);
                if (!in_array($className, $imports) && $foreignKey['table'] !== $table) {
                    $imports[] = $namespace . '\\' . $className;
                }
            }
            $inverseForeignKeys = $this->findForeignKeysInOtherTables($table);
            $this->createClass($namespace, $imports, $table, $fields, $foreignKeys, $inverseForeignKeys, $path);
        }
    }

    public function createFields(array $fields): string
    {
        $output = '';
        foreach ($fields as $field) {
            $fieldName = $this->fieldCasing->convert($field['Field']);
            $comment = $field['Type']['comment'];
            if ($comment !== '') {
                $output .= "    /** $comment */\n";
            }
            $output .= "    public " . $field['Type']['type'] . " $" . $fieldName . ";\n";
        }
        return $output;
    }

    public function createForeignKeyFields(array $foreignKeys, bool $arrays = false): string
    {
        $output = '';
        foreach ($foreignKeys as $foreignKey) {
            $fieldName = $this->fieldCasing->convert($foreignKey['field']);
            if ($this->removePlural && !$arrays) {
                $fieldName = rtrim($fieldName, 's');
            }
            $className = $this->getClassName($foreignKey['table']);
            if ($arrays) {
                $output .= "    public array $" . $fieldName . ";\n";
            } else {
                $output .= "    public " . $className . " $" . $fieldName . ";\n";
            }
        }
        return $output;
    }

    private function findForeignKeysInOtherTables(string $table) : array
    {
        $foreignKeys = array();
        $result = $this->connection->query("SELECT * FROM information_schema.KEY_COLUMN_USAGE WHERE REFERENCED_TABLE_NAME = '$table'");
        while ($row = $result->fetch_assoc()) {
            $foreignKeys[] = [
                'table' => $row['TABLE_NAME'],
                'field' => $this->parseConstraintName($row['CONSTRAINT_NAME']),
            ];
        }
        return $foreignKeys;
    }

    private function parseConstraintName(string $name): string
    {
        if (!$this->parseConstraints) {
            return $name;
        }
        $outName = str_replace('_fk_', '', $name);
        $outName = str_replace('_fk', '', $outName);
        $outName = str_replace('_id', '', $outName);
        return substr($outName, 0, strrpos($outName, '_'));
    }
}