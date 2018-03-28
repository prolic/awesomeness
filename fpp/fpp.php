<?php
/**
 * This file is part of prolic/fpp.
 * (c) 2018 Sascha-Oliver Prolic <saschaprolic@googlemail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Fpp;

if (! isset($argv[1])) {
    echo 'Missing input directory or file argument';
    exit(1);
}

function customDump(DefinitionCollection $collection, callable $locatePsrPath, callable $loadTemplate, callable $replace): void
{
    $codePrefix = <<<CODE
<?php

declare(strict_types=1);


CODE;

    $data = [];

    foreach ($collection->definitions() as $definition) {
        $constructors = $definition->constructors();

        $isEnum = false;
        $enum = new Deriving\Enum();

        foreach ($definition->derivings() as $deriving) {
            if ($deriving->equals($enum)) {
                $isEnum = true;
                break;
            }
        }

        if (1 === count($constructors)) {
            $constructor = $constructors[0];
            $file = $locatePsrPath($definition, $constructor);
            $code = $codePrefix . $replace($loadTemplate($definition, $constructor), $definition, $constructor, $collection);
            $data[$file] = substr($code, 0, -1);
        } elseif ($isEnum) {
            $file = $locatePsrPath($definition, null);
            $code = $codePrefix . $replace($loadTemplate($definition, null), $definition, null, $collection);
            $data[$file] = substr($code, 0, -1);
        } else {
            $createBaseClass = true;

            foreach ($constructors as $constructor) {
                $name = str_replace($definition->namespace() . '\\', '', $constructor->name());

                if ($definition->name() === $name) {
                    $createBaseClass = false;
                }

                $file = $locatePsrPath($definition, $constructor);
                $code = $codePrefix . $replace($loadTemplate($definition, $constructor), $definition, $constructor, $collection);
                $data[$file] = substr($code, 0, -1);
            }

            if ($createBaseClass) {
                $file = $locatePsrPath($definition, null);
                $code = $codePrefix . $replace($loadTemplate($definition, null), $definition, null, $collection);
                $data[$file] = substr($code, 0, -1);
            }
        }
    }

    foreach ($data as $file => $code) {
        $dir = dirname($file);

        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        file_put_contents($file, $code);
    }
}

$customReplace = function(
    string $template,
    Definition $definition,
    ?Constructor $constructor,
    DefinitionCollection $collection
): string {
    $builders = defaultBuilders();
    $builders['setters'] = function (Definition $definition, ?Constructor $constructor, DefinitionCollection $collection, string $placeHolder): string {
        return $placeHolder;
    };

    return replace($template, $definition, $constructor, $collection, $builders);
};

$path = $argv[1];

$autoloader = require __DIR__ . '/../vendor/prolic/fpp/src/bootstrap.php';

$prefixesPsr4 = $autoloader->getPrefixesPsr4();
$prefixesPsr0 = $autoloader->getPrefixes();

$locatePsrPath = function (Definition $definition, ?Constructor $constructor) use ($prefixesPsr4, $prefixesPsr0): string {
    return locatePsrPath($prefixesPsr4, $prefixesPsr0, $definition, $constructor);
};

$derivingMap = defaultDerivingMap();

$collection = new DefinitionCollection();

try {
    foreach (scan($path) as $file) {
        $collection = $collection->merge(parse($file, $derivingMap));
    }
} catch (ParseError $e) {
    echo 'Parse Error: ' . $e->getMessage();
    exit(1);
}

try {
    customDump($collection, $locatePsrPath, loadTemplate, $customReplace);
} catch (\Exception $e) {
    echo 'Exception: ' . $e->getMessage();
    exit(1);
}

echo "Successfully generated and written to disk\n";
exit(0);
