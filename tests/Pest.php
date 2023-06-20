<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "uses()" function to bind a different classes or traits.
|
*/

// uses(Tests\TestCase::class)->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

use Librarian\Builder\StaticBuilder;
use Librarian\Provider\ContentServiceProvider;
use Librarian\Provider\FeedServiceProvider;
use Librarian\Provider\LibrarianServiceProvider;
use Librarian\Provider\TwigServiceProvider;
use Minicli\App;

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function getLibrarian(): App
{
    $app = new App(getDefaultAppConfig());

    $builder = Mockery::mock(StaticBuilder::class);
    $builder->shouldReceive('load');
    $builder->shouldReceive('cleanup');
    $builder->shouldReceive('copyPublicResources');
    $builder->shouldReceive('buildPaginatedIndex');
    $builder->shouldReceive('buildPaginatedTagPage');
    $builder->shouldReceive('buildContentType');
    $builder->shouldReceive('getListingPage');
    $builder->shouldReceive('getSinglePage');
    $builder->shouldReceive('buildRssFeed');

    $app->addService('twig', new TwigServiceProvider());
    $app->addService('librarian', new LibrarianServiceProvider());
    $app->addService('content', new ContentServiceProvider());
    $app->addService('feed', new FeedServiceProvider());
    $app->addService('builder', $builder);

    $app->librarian->boot();

    return $app;
}

function getDefaultAppConfig(): array
{
    return [
        'app_path' => [
            __DIR__ . '/../Command'
        ],
        'data_path' => __DIR__ . '/Resources/data',
        'cache_path' => __DIR__ . '/Resources/cache',
        'templates_path' => __DIR__ . '/Resources/templates',
        'debug' => true,
        'output_path' => __DIR__ . '/Resources/output',
        'assets_path' => __DIR__ . '/Resources/assets',
        'posts_per_page' => 10
    ];
}

function getCustomIndexPageApp(): App
{
    $config = getDefaultAppConfig();
    $config['site_index'] = 'posts/test0';
    $config['site_index_tpl'] = 'content/index.html.twig';

    $app = new App($config);
    $app->addService('twig', new TwigServiceProvider());
    $app->addService('librarian', new LibrarianServiceProvider());
    $app->addService('content', new ContentServiceProvider());
    $app->addService('feed', new FeedServiceProvider());
    $app->addService('builder', new StaticBuilder());
    $app->librarian->boot();

    return $app;
}
