<?php

test('command "build" is correctly loaded', function () {
    $app = getLibrarian();
    $app->runCommand(['minicli', 'help']);
})->expectOutputRegex("/librarian build/");

test('command "build" calls methods from StaticBuilder', function () {
    $app = getLibrarian();
    $app->runCommand(['minicli', 'build']);
})->expectOutputRegex("/Finished building static website/");