<?php

namespace TargonIndustries\Scaffolder;

class EnumCreatorJS
{
    private ScaffoldingSettings $settings;

    public function __construct(ScaffoldingSettings $settings)
    {
        $this->settings = $settings;
    }

    private function getClassName(string $name): string
    {
        return $this->settings->classCasing->convert($name);
    }

    public function createEnum(string $namespace, string $name, array $values, string $nameField, string $valueField, string $path): void
    {
        $className = $this->getClassName($name);
        $output = "export class $className {\n";
        foreach ($values as $value) {
            $output .= "    static " . $this->settings->fieldCasing->convert($value[$nameField]) . " = '" . $value[$valueField] . "';\n";
        }
        $output .= "}\n";
        FileWriter::save($path . '/' . $className . '.mjs', $output, $this->settings->overwrite);
    }
}
