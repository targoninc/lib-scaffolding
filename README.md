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

require_once './MySqlScaffolder.php';

$scaffolder = new MySqlScaffolder();
try {
    $scaffolder->scaffold('name\space', './models/somepath', 'somedb');
} catch (Exception $e) {
    echo $e->getMessage();
}
```