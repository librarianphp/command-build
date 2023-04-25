<?php

use Librarian\Builder\StaticBuilder;
use Librarian\Provider\ContentServiceProvider;
use Librarian\Provider\LibrarianServiceProvider;
use Librarian\Provider\TwigServiceProvider;
use Minicli\App;

beforeEach(function() {
    $app = new App(getDefaultAppConfig());
    $app->addService('builder', new StaticBuilder());
    $app->addService('twig', new TwigServiceProvider());
    $app->addService('librarian', new LibrarianServiceProvider());
    $app->addService('content', new ContentServiceProvider());

    $app->librarian->boot();
    $this->app = $app;
});

test('StaticBuilder builds paginated index page', function () {
    /** @var StaticBuilder $builder */
    $builder = $this->app->builder;
    $builder->buildPaginatedIndex();
    expect(is_file($builder->outputPath . '/index.html'))->toBeTrue()
        ->and(is_dir($builder->outputPath . '/page'))->toBeTrue();
});

test('StaticBuilder builds paginated content types pages', function () {
    /** @var StaticBuilder $builder */
    $builder = $this->app->builder;
    $builder->buildContentType('posts');
    expect(is_file($builder->outputPath . '/posts/index.html'))->toBeTrue()
        ->and(is_file($builder->outputPath . '/posts/test0/index.html'))->toBeTrue()
        ->and(is_dir($builder->outputPath . '/posts/page'))->toBeTrue();
});

test('StaticBuilder builds custom index page', function () {
    $app = getCustomIndexPageApp();
    $app->builder->buildPaginatedIndex();
    expect(is_file($app->builder->outputPath . '/index.html'))->toBeTrue();

    $content = file_get_contents($app->builder->outputPath . '/index.html');
    expect($content)->toMatch("/template single/");
});

test('StaticBuilder builds paginated tag pages', function () {
    /** @var StaticBuilder $builder */
    $builder = $this->app->builder;
    $builder->buildPaginatedTagPage('test');
    expect(is_file($builder->outputPath . '/tag/test/index.html'))->toBeTrue()
        ->and(is_dir($builder->outputPath . '/tag/test/page'))->toBeTrue();
});

test('StaticBuilder copies asset resources to public dir', function () {
    /** @var StaticBuilder $builder */
    $builder = $this->app->builder;
    $builder->copyPublicResources();
    expect(is_file($builder->outputPath . '/css/test.css'))->toBeTrue();
});

test('StaticBuilder cleans up output dir', function () {
    /** @var StaticBuilder $builder */
    $builder = $this->app->builder;
    $builder->cleanUp();
    expect(is_file($builder->outputPath . '/index.html'))->toBeFalse();
});
