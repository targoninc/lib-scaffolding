# Description

This is a simple scaffolder for MySQL databases. It will generate a model for each table in the specified databases.
It will resolve the relations between the tables and generate properties for foreign keys in both directions.

# Example usage

## Environment variables

| Variable | Description                  |
|----------|------------------------------|
| DB_HOST  | The host of the database     |
| DB_USER  | The user of the database     |
| DB_PASS  | The password of the database |
| DB_NAME  | The name of the database     |

```php
<?php

use TargonIndustries\Scaffolder\MySqlScaffolder;
use TargonIndustries\Scaffolder\ScaffoldingLanguage;

require_once './MySqlScaffolder.php';

$scaffolder = new MySqlScaffolder();
$scaffolder->settings->useArrayConstructors(true);
try {
    $scaffolder->scaffold(ScaffoldingLanguage::JS, 'name\space', './models/somepath/js', 'somedb');
    // Also supports PHP:
    $scaffolder->scaffold(ScaffoldingLanguage::PHP, 'name\space', './models/somepath/php', 'somedb');
} catch (Exception $e) {
    echo $e->getMessage();
}
```

After this, you'll find a file for each table in the database in the specified path. The property types reference the generated classes.