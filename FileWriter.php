<?php

namespace TargonIndustries\Scaffolder;

class FileWriter
{
    static function save(string $fullPath, string $content, bool $overwrite = false): void
    {
        $folder = dirname($fullPath);
        if (!file_exists($folder)) {
            mkdir($folder, 0777, true);
        }
        if (!$overwrite && file_exists($fullPath)) {
            return;
        }
        file_put_contents($fullPath, $content);
    }
}