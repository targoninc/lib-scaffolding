<?php

namespace TargonIndustries\Scaffolder;

class ScaffoldingSettings
{
    public FieldCasing $fieldCasing = FieldCasing::CamelCase;
    public FieldCasing $classCasing = FieldCasing::PascalCase;
    public bool $saveAfterCreate = true;
    public bool $removePlural = true;
    public bool $parseConstraints = true;
    public bool $includeImports = true;
    public bool $useSameNamespace = false;
    public bool $useArrayConstructors = false;
    public bool $overwrite = true;
    public bool $alwaysNullable = false;

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

    public function includeImports(bool $include): void
    {
        $this->includeImports = $include;
    }

    public function useSameNamespace(bool $use): void
    {
        $this->useSameNamespace = $use;
    }

    public function useArrayConstructors(bool $use): void
    {
        $this->useArrayConstructors = $use;
    }

    public function overwrite(bool $overwrite): void
    {
        $this->overwrite = $overwrite;
    }

    public function alwaysNullable(bool $alwaysNullable): void
    {
        $this->alwaysNullable = $alwaysNullable;
    }
}