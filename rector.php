<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Doctrine\Set\DoctrineSetList;
use Rector\Php80\Rector\Class_\ClassPropertyAssignToConstructorPromotionRector;
use Rector\Php81\Rector\FuncCall\NullToStrictStringFuncCallArgRector;
use Rector\Symfony\Set\SymfonySetList;
use Rector\ValueObject\PhpVersion;

return RectorConfig::configure()
    // including paths
    ->withPaths([
        __DIR__ . '/src',
    ])
    // excluding paths
    ->withSkip([
        __DIR__ . '/migrations',
        __DIR__ . '/src/Kernel.php',
        __DIR__ . '/vendor',
        __DIR__ . '/var',
        __DIR__ . '/tests',
        __DIR__ . '/public/index.php',
        __DIR__ . '/config/bundles.php',
        __DIR__ . '/config/reference.php',
        __DIR__ . '/config/preload.php',
        NullToStrictStringFuncCallArgRector::class,
        // Bad fit for Doctrine entities (Properties inside __construct)
        ClassPropertyAssignToConstructorPromotionRector::class => [
            __DIR__ . '/src/Entity/*',
        ],
    ])
    ->withPhpVersion(PhpVersion::PHP_85)
    ->withImportNames(removeUnusedImports: true)

    // PHP rules
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        //                codingStyle: true,
        typeDeclarations: true,
        privatization: true,
        //                naming: true,
        instanceOf: true,
        earlyReturn: true,
        doctrineCodeQuality: true,
        symfonyCodeQuality: true,
        symfonyConfigs: true
    )

    // Symfony auto rules
    ->withComposerBased(twig: true, doctrine: true, phpunit: true, symfony: true)
    ->withAttributesSets(symfony: true, doctrine: true)
    // Auto Php Select
    ->withPhpSets()
    // Add Symfony custom rules
    ->withSets([
        SymfonySetList::SYMFONY_CODE_QUALITY,
        SymfonySetList::CONFIGS,
        SymfonySetList::SYMFONY_CONSTRUCTOR_INJECTION,
        DoctrineSetList::DOCTRINE_CODE_QUALITY,
    ]);
