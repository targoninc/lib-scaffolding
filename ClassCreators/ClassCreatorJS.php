<?php

namespace TargonIndustries\Scaffolder;

use Exception;

class ClassCreatorJS
{
    private ScaffoldingSettings $settings;

    public function __construct(ScaffoldingSettings $settings)
    {
        $this->settings = $settings;
    }

    private function createConstructor(array $fields, array $foreignKeys, array $inverseForeignKeys): string
    {
        return '';
    }

    private function createArrayConstructor(array $fields, array $foreignKeys, array $inverseForeignKeys): string
    {
        $output = "    constructor(data) {\n";
        foreach ($fields as $field) {
            $fieldName = $this->settings->fieldCasing->convert($field['Field']);
            $output .= "        this." . $fieldName . " = data." . $fieldName . ";\n";
        }
        foreach ($foreignKeys as $foreignKey) {
            $fieldName = $this->settings->fieldCasing->convert($foreignKey['field']);
            $output .= "        this." . $fieldName . " = data." . $fieldName . ";\n";
        }
        foreach ($inverseForeignKeys as $foreignKey) {
            $fieldName = $this->settings->fieldCasing->convert($foreignKey['field']);
            $output .= "        this." . $fieldName . " = data." . $fieldName . ";\n";
        }
        $output .= "    }\n";
        return $output;
    }

    /**
     * @throws Exception if any of the field types are not recognized
     */
    public function createClass(string $namespace, string $name, array $fields, array $foreignKeys, array $inverseForeignKeys, string $path): string
    {
        $className = $this->getClassName($name);

        $fields = $this->mapTypes($fields);
        $imports = array();
        foreach ($foreignKeys as $foreignKey) {
            $newImport = $this->getClassName($foreignKey['type']);
            if (!in_array($className, $imports) && $foreignKey['type'] !== $name) {
                $imports[] = $newImport;
            }
        }
        foreach ($inverseForeignKeys as $foreignKey) {
            $newImport = $this->getClassName($foreignKey['type']);
            if (!in_array($newImport, $imports) && $foreignKey['type'] !== $name) {
                $imports[] = $newImport;
            }
        }
        foreach ($fields as $field) {
            $imports[] = $field['Type'];
        }

        $class = $this->createImports($imports);

        $class .= "\nexport class " . $className . " {\n";
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
            FileWriter::save($path . '/' . $className . '.mjs', $class, $this->settings->overwrite);
        }
        return $class;
    }

    private function createImports(array $imports): string
    {
        $output = '';
        if ($this->settings->includeImports) {
            $requireBase = "import { ";
            foreach ($imports as $import) {
                $className = explode('\\', $import)[count(explode('\\', $import)) - 1];
                if (in_array($className, LanguageInfoJS::$nativeTypes)) {
                    continue;
                }
                $output .= $requireBase . $className . " } from '" . $className . ".mjs';\n";
            }
        }
        return $output;
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
    public function mapType(string $type): string
    {
        $baseType = explode('(', $type)[0];
        return match ($baseType) {
            'varchar', 'longtext', 'text', 'timestamp', 'date' => 'string',
            'decimal', 'double', 'float', 'int', 'bigint' => 'number',
            'tinyint', 'bit' => 'boolean',
            'datetime' => 'Date',
            default => throw new Exception('Unexpected value: ' . $baseType),
        };
    }

    public function createFields(array $fields): string
    {
        $output = '';
        foreach ($fields as $field) {
            $fieldName = $this->settings->fieldCasing->convert($field['Field']);
            $fieldType = $field['Type'];
            $nullable = $field['Null'] === 'YES' ? ' = null' : '';
            $nullableDoc = $field['nullable'] ? '|null' : '';
            $output .= "    /** @var {" . $fieldType . $nullableDoc . "} $fieldName */\n";
            $output .= "    " . $fieldName . $nullable . ";\n";
        }
        return $output;
    }

    public function createForeignKeyFields(array $foreignKeys, bool $arrays = false): string
    {
        $output = '';
        foreach ($foreignKeys as $foreignKey) {
            $fieldName = $this->settings->fieldCasing->convert($foreignKey['field']);
            if ($this->settings->removePlural && !$arrays) {
                $fieldName = rtrim($fieldName, 's');
            }
            $fieldType = $this->getClassName($foreignKey['type']);

            $array = $arrays ? '[]' : '';
            $nullable = $foreignKey['nullable'] ? ' = null' : '';
            $nullableDoc = $foreignKey['nullable'] ? '|null' : '';
            $output .= "    /** @var {" . $fieldType . $array . $nullableDoc . "} $fieldName */\n";
            $output .= "    " . $fieldName . $nullable . ";\n";
        }
        return $output;
    }

    private function getClassName(string $name): string
    {
        $className = $this->settings->classCasing->convert($name);
        if ($this->settings->removePlural) {
            $className = rtrim($className, 's');
        }
        return $className;
    }
}