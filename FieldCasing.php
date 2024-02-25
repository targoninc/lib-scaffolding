<?php

namespace TargonIndustries\Scaffolder;

enum FieldCasing
{
    case CamelCase;
    case PascalCase;
    case SnakeCase;
    case None;

    public function convert(string $name): string
    {
        $name = preg_replace('/\s+/', '_', $name);
        return match ($this) {
            FieldCasing::CamelCase => lcfirst(str_replace('_', '', ucwords($name, '_'))),
            FieldCasing::PascalCase => str_replace('_', '', ucwords($name, '_')),
            FieldCasing::SnakeCase => strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $name)),
            default => $name,
        };
    }
}
