# vLibTemplate 5 for php8

This package aims to extend life for legacy PHP projects still using vLibTemplate library and wanting to use it with [Composer](https://getcomposer.com) 

## Original library

Library is originally created by: Kelvin Jones, Stefan Deußen and Claus van Beek

Official Website is http://vlib.clausvb.de/index.php/Main_Page
The official site is not working correctly anymore.

Original library download link: [SourceForge](https://sourceforge.net/projects/vlib/)

Copy of documentation for original library: https://acdh-oeaw.github.io/vLIB/docs/vlib.clausvb.de/docs/vlibTemplate_english/table_of_content.html

## The purpose

The original package was created 20+ years ago and refers in code to PHP4&5 that is very
outdated now. Many projects still run perfectly fine using this library, but migrating to
latest PHP might be problematic, so this modified new version could help this transfer.
The last original library version was 4.2.1 this project uses version 5.0.0 as starting point.

The aim of this package:
- Modify the original classes as little as possible, to keep maximum compatibility with existing code
- Remove any functions marked as deprecated for latest stable PHP version 
- Make it PHP8.4+ compatible
- Add types, modernize code syntax.
- Publish in Packagist for easy install
- Try to minimise the implementation/configuration work
- Countinue to keep package compatible in next PHP versions
- All includes moved to autoload
- Reduce clutter and package size, removing outdated parts (see next)

This package implements (at least for now) only these parts:
- vLibTemplate
- vLibTemplateCache
- vLibInit

Other parts as vlibDate, Swift, vlibMimeMail, vlibSearchToSQL, etc. are **NOT** included!
I doubt they will ever be (I do no need them :) ) and for email it is better to implement
modern alternatives. But if someone finds useful to add some of these parts - welcome to contribute!

Original, **but modified** classes are put under namespace "Original". 
If you can not use Composer, but need PHP8 compatibility - you can try to hack with "includes"
this folders source in your existing project. This is reason the class 
names are not changed, but put under namespace.

## Usage

### Installation
```bash 
  composer require redbird/vlib-template5
```

### Example codes

### Bare minimum without vlibIni (not recomended) or only for single-page aplications. 

```php
<?php

declare(strict_types=1);

require ('/vendor/autoload.php');

$tmpl = new Redbird\vlib5\vlibTemplate('patht/to/templates/template.html');
$tmpl->setVar('name','Test Name');
$tmpl->pparse();
```

### Normal use, with vlibIni (recomended)

```php
<?php

declare(strict_types=1);

use Redbird\vlib5\vlibIni;
use Redbird\vlib5\vlibTemplate;

require ('/vendor/autoload.php');

vlibIni::setup([
    'TEMPLATE_DIR' => '/path/to/templates/', //change for the place templates are stored
    //... See src/vlibIni.php source for all options
]);

$tmpl = new vlibTemplate('template.html');
$tmpl->setVar('name','Test Name');
$tmpl->setloop('loop', [
     ['id' => 1, 'name' => 'Claus'],
     ['id' => 2, 'name' => 'Kelvin'],
     ['id' => 3, 'name' => 'Skrol'],
     ['id' => 4, 'name' => 'Micheal'],
]);
$tmpl->pparse();
```

## Be awere

This new version does not gurantee any security improvements. As in this old code some bugs somebody
could use as "feature" - mostly all logic is kept as is.

Speaking of changes:

- By default now non-array variables used in setLoop are set ar empty arrays. 
This can be turned off by "LOOP_NOT_ARRAY_OVERRIDE" param.
- Default `CACHE_DIRECTORY` is set to `sys_get_temp_dir()`. This is ok, for testing and dev,
but highly unadvisible in production environments.
- DB engine list in `setDbLoop()` has been shortend and is **not tested(!)**. 
No way for me to test all engines and depraceted functions where removed. 
I do not use this function and recomend to convert data to array first and then use `setLoop()`.
- One more reminder that classes in `src/Original` are modified, let the folder name not confuse you! :)

## Contributing

Welcome contributions! For now I do not know if this project is helpful to anyone elso except me.
No guidlines, just contact me.

## Contributors

[Edgars Strods](https://github.com/edgarsstrods)

## License

The original licence is kept under [LICENSE.txt](src/Original/LICENSE.txt)

## Support

If I have helped to migrate a project and saved a lot of work - buy me a coffee, thanks! :)


