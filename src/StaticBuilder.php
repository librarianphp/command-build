<?php

namespace Librarian\Builder;

use Exception;
use Librarian\Content;
use Librarian\ContentCollection;
use Librarian\ContentType;
use Librarian\Exception\ContentNotFoundException;
use Librarian\Provider\ContentServiceProvider;
use Librarian\Provider\FeedServiceProvider;
use Librarian\Provider\TwigServiceProvider;
use Minicli\App;
use Minicli\Config;
use Minicli\ServiceInterface;
use Suin\RSSWriter\Channel;
use Suin\RSSWriter\Feed;
use Suin\RSSWriter\Item;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class StaticBuilder implements ServiceInterface
{
    public Config $siteConfig;
    public ContentServiceProvider $contentProvider;
    public FeedServiceProvider $feedProvider;
    public TwigServiceProvider $twigServiceProvider;
    public string $outputPath;
    public int $postsPerPage;

    public function load(App $app): void
    {
        $this->contentProvider = $app->content;
        $this->feedProvider = $app->feed;
        $this->twigServiceProvider = $app->twig;
        $this->siteConfig = $app->config;
        $this->outputPath = $this->siteConfig->output_path;
        $this->postsPerPage = $this->siteConfig->posts_per_page;
    }

    public function cleanUp(): void
    {
        shell_exec("rm -rf $this->outputPath/*");
    }

    public function saveFile(string $path, string $content): void
    {
        file_put_contents($path, $content);
    }

    public function copyPublicResources(): void
    {
        $publicResources = $this->siteConfig->assets_path;
        shell_exec("cp -R $publicResources/* $this->outputPath");
    }

    /**
     * @throws ContentNotFoundException
     * @throws SyntaxError
     * @throws RuntimeError
     * @throws LoaderError
     */
    public function buildPaginatedIndex(): void
    {
        $pages = $this->contentProvider->fetchTotalPages($this->postsPerPage);

        for ($i = 1; $i <= $pages; $i++) {
            $pageOutputDir = $this->outputPath . '/page/' . $i;
            if (!is_dir($pageOutputDir)) {
                mkdir($pageOutputDir, 0777, true);
            }

            $start = ($i * $this->postsPerPage) - $this->postsPerPage;
            $contentList = $this->contentProvider->fetchAll($start, $this->postsPerPage);
            $this->saveFile($pageOutputDir . '/index.html', $this->getListingPage($i, $pages, $contentList));
        }

        $indexPage = $this->getIndexPage();
        $this->saveFile($this->outputPath . '/index.html', $indexPage);
    }

    /**
     * @throws RuntimeError
     * @throws SyntaxError
     * @throws LoaderError
     */
    public function getIndexPage(): string
    {
        $indexPage = null;

        if ($this->siteConfig->site_index !== null) {
            $pageTpl = $this->siteConfig->site_index_tpl ?? 'content/single.html.twig';
            $page = $this->contentProvider->fetch($this->siteConfig->site_index);
            $indexPage =  $this->twigServiceProvider->render($pageTpl, [
                'content' => $page,
            ]);
        }

        if ($indexPage === null) {
            $pageOne = $this->outputPath . '/page/1/index.html';
            if (is_file($pageOne)) {
                $indexPage = file_get_contents($this->outputPath . '/page/1/index.html');
            }
        }

        return $indexPage;
    }

    /**
     * @throws SyntaxError
     * @throws RuntimeError
     * @throws LoaderError
     * @throws Exception
     */
    public function buildPaginatedTagPage(string $tag): void
    {
        $pages = $this->contentProvider->fetchTagTotalPages($tag, $this->postsPerPage);
        if (!is_dir($this->outputPath . '/tag/' . $tag)) {
            mkdir($this->outputPath . '/tag/' . $tag, 0777, true);
        }

        for ($i = 1; $i <= $pages; $i++) {
            $pageOutputDir = $this->outputPath . "/tag/$tag/page/$i";
            if (!is_dir($pageOutputDir)) {
                mkdir($pageOutputDir, 0777, true);
            }

            $start = ($i * $this->postsPerPage) - $this->postsPerPage;
            $contentList = $this->contentProvider->fetchFromTag($tag, $start, $this->postsPerPage);
            $this->saveFile($pageOutputDir . '/index.html', $this->getListingPage($i, $pages, $contentList));
        }

        $pageOne = $this->outputPath . "/tag/$tag/page/1/index.html";
        if (is_file($pageOne)) {
            copy($pageOne, $this->outputPath . "/tag/$tag/index.html");
        }
    }

    /**
     * @throws SyntaxError
     * @throws RuntimeError
     * @throws LoaderError
     */
    public function buildContentType(ContentType $contentType): void
    {
        if (!is_dir($this->outputPath . '/' . $contentType->slug)) {
            mkdir($this->outputPath . '/' . $contentType->slug);
        }

        $totalPosts = 0;
        /** @var Content $contentPost */
        foreach ($this->contentProvider->fetchFrom($contentType, 0, 1000, true) as $contentPost) {
            $contentDir = $this->outputPath . '/' . $contentType->slug . '/' . $contentPost->getSlug();
            if (!is_dir($contentDir)) {
                mkdir($contentDir);
            }

            $this->saveFile($contentDir . '/index.html', $this->getSinglePage($contentPost));
            $totalPosts++;
        }

        $pages = ceil($totalPosts / $this->postsPerPage);
        for ($i = 1; $i <= $pages; $i++) {
            $pageOutputDir = $this->outputPath . '/' . $contentType->slug . '/page/' . $i;
            if (!is_dir($pageOutputDir)) {
                mkdir($pageOutputDir, 0777, true);
            }

            $start = ($i * $this->postsPerPage) - $this->postsPerPage;

            $contentList = $this->contentProvider->fetchFrom($contentType, $start, $this->postsPerPage);
            $this->saveFile($pageOutputDir . '/index.html', $this->getListingPage($i, $pages, $contentList, $contentType));
        }
        $pageOne = $this->outputPath . '/' . $contentType->slug . '/page/1/index.html';
        if (is_file($pageOne)) {
            copy($pageOne, $this->outputPath . '/' . $contentType->slug . '/index.html');
        }
    }

    /**
     * @throws SyntaxError
     * @throws RuntimeError
     * @throws LoaderError
     */
    public function getListingPage(int $page, int $totalPages, ContentCollection $contentList, ?ContentType $contentType = null): string
    {
        return $this->twigServiceProvider->render('content/listing.html.twig', [
            'content_list'  => $contentList,
            'total_pages' => $totalPages,
            'current_page' => $page,
            'content_type' => $contentType
        ]);
    }

    /**
     * @throws SyntaxError
     * @throws RuntimeError
     * @throws LoaderError
     */
    public function getSinglePage(Content $content): string
    {
        return $this->twigServiceProvider->render('content/single.html.twig', [
            'content' => $content,
        ]);
    }

    public function buildRssFeed(): void
    {
        $feed = $this->feedProvider->buildFeed(is_static: true);
        $customFeedPath = $this->feedProvider->getCustomFeedPath(is_static: true);

        $this->saveFile($this->outputPath . $customFeedPath, $feed);
    }
}
