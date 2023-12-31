<?php

namespace TargonIndustries\Scaffolder;

use Exception;

class ClassCreatorPHP
{
    private ScaffoldingSettings $settings;

    public function __construct(ScaffoldingSettings $settings)
    {
        $this->settings = $settings;
    }

    private function createConstructor(array $fields, array $foreignKeys, array $inverseForeignKeys): string
    {
        $output = "    public function __construct(";
        $params = array();
        foreach ($fields as $field) {
            $params[] = $field['Type']['type'] . " $" . $this->settings->fieldCasing->convert($field['Field']);
        }
        foreach ($foreignKeys as $foreignKey) {
            $className = $this->getClassName($foreignKey['type']);
            $fieldName = $foreignKey['field'];
            if ($this->settings->removePlural) {
                $fieldName = rtrim($fieldName, 's');
            }
            $params[] = $className . " $" . $this->settings->fieldCasing->convert($fieldName);
        }
        foreach ($inverseForeignKeys as $foreignKey) {
            $fieldName = $foreignKey['field'];
            $params[] = "array $" . $this->settings->fieldCasing->convert($fieldName);
        }
        $output .= implode(', ', $params);
        $output .= ")\n    {\n";
        foreach ($fields as $field) {
            $fieldName = $this->settings->fieldCasing->convert($field['Field']);
            $output .= "        \$this->$fieldName = \$$fieldName;\n";
        }
        foreach ($foreignKeys as $foreignKey) {
            $fieldName = $this->settings->fieldCasing->convert($foreignKey['field']);
            if ($this->settings->removePlural) {
                $fieldName = rtrim($fieldName, 's');
            }
            $output .= "        \$this->$fieldName = \$$fieldName;\n";
        }
        foreach ($inverseForeignKeys as $foreignKey) {
            $fieldName = $this->settings->fieldCasing->convert($foreignKey['field']);
            $output .= "        \$this->$fieldName = \$$fieldName;\n";
        }
        $output .= "    }\n";
        return $output;
    }

    private function createArrayConstructor(array $fields, array $foreignKeys, array $inverseForeignKeys): string
    {
        $output = '    public function __construct(array $input)' . "\n    {\n";
        $nullableSuffix = $this->settings->alwaysNullable ? ' ?? null' : '';
        foreach ($fields as $field) {
            $rawName = $field['Field'];
            $fieldName = $this->settings->fieldCasing->convert($rawName);
            $output .= "        \$this->$fieldName = \$input['$rawName']$nullableSuffix;\n";
        }
        foreach ($foreignKeys as $foreignKey) {
            if (in_array($foreignKey['database'], $this->settings->ignoredDatabases)) {
                echo "Ignoring foreign key " . $foreignKey['field'] . " in database " . $foreignKey['database'] . " for constructor\n";
                continue;
            }
            $rawName = $foreignKey['field'];
            $fieldName = $this->getFieldName($rawName);
            $output .= "        \$this->$fieldName = \$input['$rawName']$nullableSuffix;\n";
        }
        foreach ($inverseForeignKeys as $foreignKey) {
            if (in_array($foreignKey['database'], $this->settings->ignoredDatabases)) {
                echo "Ignoring foreign key " . $foreignKey['field'] . " in database " . $foreignKey['database'] . " for constructor\n";
                continue;
            }
            $rawName = $foreignKey['field'];
            $fieldName = $this->settings->fieldCasing->convert($rawName);
            $output .= "        \$this->$fieldName = \$input['$rawName']$nullableSuffix;\n";
        }
        $output .= "    }\n";
        return $output;
    }

    /**
     * @throws Exception if any of the field types are not recognized
     */
    public function createClass(string $namespace, string $name, array $fields, array $foreignKeys, array $inverseForeignKeys, string $path): string
    {
        $class = "<?php\n\nnamespace $namespace;\n\n";

        $fields = $this->mapTypes($fields);
        $imports = array();
        $imports = $this->getForeignKeyImports($foreignKeys, $imports, $name, $namespace);
        $imports = $this->getForeignKeyImports($inverseForeignKeys, $imports, $name, $namespace);
        foreach ($fields as $field) {
            $imports[] = $field['Type']['type'];
        }

        $className = $this->getClassName($name);
        $class .= $this->createImports($imports);

        if ($this->settings->allowDynamicProperties) {
            $class .= "\n#[AllowDynamicProperties]";
        }
        $class .= "\n\nclass $className\n{\n";
        $class .= $this->createFields($fields);
        $class .= $this->createForeignKeyFields($foreignKeys);
        $class .= $this->createForeignKeyFields($inverseForeignKeys, true);
        if ($this->settings->useArrayConstructors) {
            $class .= $this->createArrayConstructor($fields, $foreignKeys, $inverseForeignKeys);
        } else {
            $class .= $this->createConstructor($fields, $foreignKeys, $inverseForeignKeys);
        }
        $class .= "}\n";
        $class = implode("\n", array_unique(explode("\n", $class)));
        if ($this->settings->saveAfterCreate) {
            FileWriter::save($path . '/' . $className . '.php', $class, $this->settings->overwrite);
        }
        return $class;
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
            'tinyint', 'bit' => 'bool',
            'decimal', 'double', 'float' => 'float',
            'varchar', 'longtext', 'text', 'timestamp', 'date', 'datetime' => 'string',
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

    public function createFields(array $fields): string
    {
        $output = '';
        foreach ($fields as $field) {
            $fieldName = $this->settings->fieldCasing->convert($field['Field']);
            $comment = $field['Type']['comment'];
            if ($comment !== '') {
                $output .= "    /** $comment */\n";
            }
            $nullable = $field['nullable'] ? '?' : ($this->settings->alwaysNullable ? '?' : '');
            $output .= "    public " . $nullable . $field['Type']['type'] . " $" . $fieldName . ";\n";
        }
        return $output;
    }

    public function createForeignKeyFields(array $foreignKeys, bool $arrays = false): string
    {
        $output = '';
        foreach ($foreignKeys as $foreignKey) {
            if (in_array($foreignKey['database'], $this->settings->ignoredDatabases)) {
                echo "Ignoring foreign key " . $foreignKey['field'] . " in database " . $foreignKey['database'] . " for fields\n";
                continue;
            }
            $fieldName = $this->getFieldName($foreignKey['field']);
            $className = $this->getClassName($foreignKey['type']);
            if ($arrays) {
                $arrayClassName = $this->getClassName($foreignKey['type']);
                $output .= "    /** @var array<" . $arrayClassName . "> */\n";
                $output .= "    public ?array $" . $fieldName . "s;\n";
            } else {
                if ($this->settings->alwaysNullable) {
                    $className = '?' . $className;
                }
                $output .= "    public " . $className . " $" . $fieldName . ";\n";
            }
        }
        return $output;
    }

    private function getFieldName(string $name, bool $noPlural = true): string
    {
        $fieldName = $this->settings->fieldCasing->convert($name);
        if ($this->settings->removePlural && $noPlural) {
            $fieldName = rtrim($fieldName, 's');
        }
        return $fieldName;
    }

    private function getClassName(string $name): string
    {
        $className = $this->settings->classCasing->convert($name);
        if ($this->settings->removePlural) {
            $className = rtrim($className, 's');
        }
        return $className;
    }

    private function createImports(array $imports): string
    {
        $output = '';
        if ($this->settings->allowDynamicProperties) {
            $output .= "use AllowDynamicProperties;\n";
        }
        foreach ($imports as $import) {
            if (!$this->settings->useSameNamespace) {
                $className = explode('\\', $import)[count(explode('\\', $import)) - 1];
                if (!in_array($className, LanguageInfoPHP::$nativeClasses)) {
                    continue;
                }
            }
            $output .= "use $import;\n";
        }
        $output .= "\n";
        if ($this->settings->includeImports) {
            $requireBase = "require_once __DIR__ . '/";
            foreach ($imports as $import) {
                $className = explode('\\', $import)[count(explode('\\', $import)) - 1];
                if (in_array($className, LanguageInfoPHP::$nativeImports)) {
                    continue;
                }
                $output .= $requireBase . $className . ".php';\n";
            }
        }
        return $output . "\n";
    }

    /**
     * @param array $foreignKeys
     * @param array $imports
     * @param mixed $name
     * @param string $namespace
     * @return array
     */
    public function getForeignKeyImports(array $foreignKeys, array $imports, mixed $name, string $namespace): array
    {
        foreach ($foreignKeys as $foreignKey) {
            $className = $this->getClassName($foreignKey['type']);
            if (!in_array($className, $imports) && $foreignKey['type'] !== $name) {
                if (in_array($foreignKey['database'], $this->settings->ignoredDatabases)) {
                    continue;
                }
                $imports[] = $namespace . '\\' . $className;
            }
        }
        return $imports;
    }
}