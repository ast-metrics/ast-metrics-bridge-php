#!/usr/bin/env php
<?php

/**
 * Test runner. Use "make test", or "php tests/run.php".
 *
 * Every tests/*_test.php file is executed in turn; assertions come from
 * bootstrap.php. There is no test dependency on purpose: this package has to
 * install into any PHP project, including old ones, so its own tests must not
 * need anything that the project itself might not tolerate.
 */

require_once __DIR__ . '/bootstrap.php';

$files = glob(__DIR__ . '/*_test.php');
sort($files);

foreach ($files as $file) {
    TestResults::$currentFile = basename($file, '.php');
    require $file;
}

echo PHP_EOL;

if (TestResults::$failures === 0) {
    echo sprintf(
        '%d assertions in %d files, all passing.' . PHP_EOL,
        TestResults::$assertions,
        count($files)
    );
    exit(0);
}

echo sprintf(
    '%d of %d assertions failed.' . PHP_EOL,
    TestResults::$failures,
    TestResults::$assertions
);
exit(1);
