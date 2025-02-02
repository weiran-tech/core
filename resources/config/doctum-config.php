<?php

// config/doctum.php
// php ./resources/sami/sami.phar update ./resources/sami/config.php

use Doctum\Doctum;

$baseDir       = dirname(dirname(__DIR__));
$folders       = glob($baseDir . '/{modules,poppy}/*/src', GLOB_BRACE);
$vendorFolders = glob($baseDir . '/vendor/poppy/**/src', GLOB_BRACE);

$folders = array_merge($folders, $vendorFolders);

$excludes = [];
foreach ($folders as $folder) {
    $excludes[] = $folder . '/database/seeds';
    $excludes[] = $folder . '/database/migrations';
    $excludes[] = $folder . '/database/factories';
    $excludes[] = $folder . '/update';
}

$iterator = Symfony\Component\Finder\Finder::create()
    ->files()
    ->name('*.php')
    ->exclude('database')
    ->exclude('update')
    ->in($folders);

$options = [
    'theme'     => 'default',
    'title'     => 'Lemon Framework API Documentation',
    'build_dir' => $baseDir . '/public/docs/php',
    'cache_dir' => $baseDir . '/storage/doctum/cache',
];

return new Doctum($iterator, $options);