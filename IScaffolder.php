<?php

namespace TargonIndustries\Scaffolder;

use Exception;

interface IScaffolder
{
    function setConnection(mixed $connection): void;

    public function getTables(string $database): array;

    public function getFields(string $database, string $table): array;

    public function getForeignKeys(string $database, string $table): array;

    /**
     * @throws Exception
     */
    public function scaffold(ScaffoldingLanguage $language, string $namespace, string $path, string $database): void;
}