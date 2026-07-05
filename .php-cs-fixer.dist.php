<?php

declare(strict_types=1);

$finder = new PhpCsFixer\Finder()
    ->in(__DIR__)
    ->exclude([
        'var',
        'vendor',
        'config',
        'public',
    ]);

return new PhpCsFixer\Config()
    ->setRiskyAllowed(true)
    ->setRules([
        '@Symfony' => true,
        '@Symfony:risky' => true,

        // Lisibilité modernité
        'yoda_style' => false,
        'declare_strict_types' => true,
        'ordered_imports' => true,
        'no_unused_imports' => true,
        'no_superfluous_phpdoc_tags' => false,
        'array_syntax' => ['syntax' => 'short'],
        'global_namespace_import' => [
            'import_classes' => true,
            'import_constants' => true,
            'import_functions' => true,
        ],

        // PHP 8+
        'trailing_comma_in_multiline' => true,
        'native_function_invocation' => false,

        // Sécurité / clarté
        'strict_comparison' => true,
        'strict_param' => true,
    ])
    ->setFinder($finder);
