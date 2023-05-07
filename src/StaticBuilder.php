<?php

namespace Librarian\Builder;

use Librarian\Content;
use Librarian\ContentCollection;
use Librarian\Provider\ContentServiceProvider;
use Librarian\Provider\TwigServiceProvider;
use Minicli\App;
use Minicli\Config;
use Minicli\ServiceInterface;
use Suin\RSSWriter\Channel;
use Suin\RSSWriter\Feed;
use Suin\RSSWriter\Item;

class StaticBuilder implements ServiceInterface
{
    public Config $siteConfig;
    public ContentServiceProvider $contentProvider;
    public TwigServiceProvider $twigServiceProvider;
    public string $outputPath;
    public int $postsPerPage;

    public function load(App $app): void
    {
        $this->contentProvider = $app->content;
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

    public function buildContentType($contentType): void
    {
        if (!is_dir($this->outputPath . '/' . $contentType)) {
            mkdir($this->outputPath . '/' . $contentType);
        }

        $totalPosts = 0;
        /** @var Content $contentPost */
        foreach ($this->contentProvider->fetchFrom($contentType, 0, 1000, true) as $contentPost) {
            $contentDir = $this->outputPath . '/' . $contentType . '/' . $contentPost->getSlug();
            if (!is_dir($contentDir)) {
                mkdir($contentDir);
            }

            $this->saveFile($contentDir . '/index.html', $this->getSinglePage($contentPost));
            $totalPosts++;
        }

        $pages = ceil($totalPosts / $this->postsPerPage);
        for ($i = 1; $i <= $pages; $i++) {
            $pageOutputDir = $this->outputPath . '/' . $contentType . '/page/' . $i;
            if (!is_dir($pageOutputDir)) {
                mkdir($pageOutputDir, 0777, true);
            }

            $start = ($i * $this->postsPerPage) - $this->postsPerPage;

            $contentList = $this->contentProvider->fetchFrom($contentType, $start, $this->postsPerPage);
            $this->saveFile($pageOutputDir . '/index.html', $this->getListingPage($i, $pages, $contentList));
        }
        $pageOne = $this->outputPath . '/' . $contentType . '/page/1/index.html';
        if (is_file($pageOne)) {
            copy($pageOne, $this->outputPath . '/' . $contentType . '/index.html');
        }
    }

    public function getListingPage(int $page, int $totalPages, ContentCollection $contentList): string
    {
        return $this->twigServiceProvider->render('content/listing.html.twig', [
            'content_list'  => $contentList,
            'total_pages' => $totalPages,
            'current_page' => $page
        ]);
    }

    public function getSinglePage(Content $content): string
    {
        return $this->twigServiceProvider->render('content/single.html.twig', [
            'content' => $content,
        ]);
    }

    public function buildRssFeed(): void
    {
        $feed = new Feed();
        $channel = new Channel();
        $channel
            ->title($this->siteConfig->site_name)
            ->description($this->siteConfig->site_description)
            ->url($this->siteConfig->site_url)
            ->feedUrl($this->siteConfig->site_url . '/feed.rss')
            ->language('en-US')
            ->copyright('Copyright ' . date('Y') . ', '. $this->siteConfig->site_name)
            ->pubDate(strtotime(date('Y-m-d H:i:s')))
            ->lastBuildDate(strtotime(date('Y-m-d H:i:s')))
            ->ttl(60)
            ->appendTo($feed);

        $content_list = $this->contentProvider->fetchAll();

        /** @var Content $content */
        foreach ($content_list as $content) {
            $item = new Item();
            $item
                ->title($content->frontMatterGet('title') ?? $content->default_title)
                ->description('<div>'.($content->frontMatterGet('description') ?? $content->description).'</div>')
                ->contentEncoded('<div>'.($content->description ?? '').'</div>')
                ->url($this->siteConfig->site_url . '/' . $content->getLink())
                ->author($this->siteConfig->site_author)
                ->pubDate(strtotime($content->getDate()))
                ->guid($this->siteConfig->site_url . '/' . $content->getLink(), true)
                ->preferCdata(true) // By this, title and description become CDATA wrapped HTML.
                ->appendTo($channel);
        }

        $this->saveFile($this->outputPath . '/feed.rss', $feed);
    }
}
