<?php

namespace TargonIndustries\Scaffolder;

class EnumCreatorPHP
{
    private ScaffoldingSettings $settings;

    public function __construct(ScaffoldingSettings $settings)
    {
        $this->settings = $settings;
    }

    private function getClassName(string $name): string
    {
        $className = $this->settings->classCasing->convert($name);
        if ($this->settings->removePlural) {
            $className = rtrim($className, 's');
        }
        return $className;
    }

    public function createEnum(string $namespace, string $name, array $values, string $nameField, string $valueField, string $path): void
    {
        $className = $this->getClassName($name);
        $output = "<?php\n\nnamespace $namespace;\n\n";
        $output .= "class $className\n{\n";
        foreach ($values as $value) {
            $output .= "    const " . $this->settings->fieldCasing->convert($value[$nameField]) . " = '" . $value[$valueField] . "';\n";
        }
        $output .= "\n";
        $output .= $this->createArrayMethods($values, $nameField, $valueField);
        $output .= "}\n";
        FileWriter::save($path . '/' . $className . '.php', $output);
    }

    public function createArrayMethods(array $values, string $nameField, string $valueField): string
    {
        $output = "    public static function toMap(): array\n    {\n        return [\n";
        foreach ($values as $value) {
            $output .= "            '" . $value[$nameField] . "' => '" . $value[$valueField] . "',\n";
        }
        $output .= "        ];\n    }\n";
        $output .= "\n";
        $output .= "    public static function toArray(): array\n    {\n        return [\n";
        foreach ($values as $value) {
            $output .= "            '" . $value[$nameField] . "',\n";
        }
        $output .= "        ];\n    }\n";
        return $output;
    }
}
