<?php

namespace TargonIndustries\Scaffolder;

use Exception;

interface IScaffolder
{
    public function saveAfterCreate(bool $save): void;

    public function setFieldCasing(FieldCasing $casing): void;

    public function setClassCasing(FieldCasing $casing): void;

    public function removePlural(bool $remove): void;

    public function parseConstraints(bool $parse): void;

    function setConnection(mixed $connection): void;

    public function getTables(string $database): array;

    public function getFields(string $database, string $table): array;

    public function getForeignKeys(string $database, string $table): array;

    /**
     * @throws Exception if the type is not recognized
     */
    public function mapTypes(array $fields): array;

    /**
     * @throws Exception if the type is not recognized
     */
    public function mapType(string $type): array;

    /**
     * @throws Exception if any of the field types are not recognized
     */
    public function createClass(string $namespace, array $imports, string $name, array $fields, array $foreignKeys, array $inverseForeignKeys, string $path): string;

    public function saveClass(string $fullPath, string $content): void;

    /**
     * @throws Exception
     */
    public function scaffold(string $namespace, string $path, string $database): void;

    public function createFields(array $fields): string;

    public function createForeignKeyFields(array $foreignKeys, bool $arrays = false): string;
}