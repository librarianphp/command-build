# command-build

Librarian's static builder command.

```shell
./librarian build
```
## Config Requirements

Add the following `static.php` config file in Librarian's config folder:

```php
<?php

return [
    /*****************************************************************************
     * Settings for static build output
     ******************************************************************************/
    'output_path' => __DIR__ . '/../public',
    'assets_path' => __DIR__ . '/../app/Resources/public'
];

```

- `output_path`: location where to dump the static build
- `assets_path`: directory with public assets resources that should be copied into the document root of the generated website.