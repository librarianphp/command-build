<?php

test('command "build" calls methods from StaticBuilder', function () {
    $app = getLibrarian();
    $app->runCommand(['minicli', 'build']);
})->expectOutputRegex("/Finished building static website/");
