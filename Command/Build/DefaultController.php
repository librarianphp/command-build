<?php

namespace librarianphp\Build;

use Librarian\Builder\StaticBuilder;
use Librarian\ContentType;
use Librarian\Exception\ContentNotFoundException;
use Librarian\Provider\ContentServiceProvider;
use Minicli\Command\CommandController;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class DefaultController extends CommandController
{
    /**
     * @throws ContentNotFoundException
     * @throws RuntimeError
     * @throws LoaderError
     * @throws SyntaxError
     */
    public function handle(): void
    {
        $outputDir = $this->getApp()->config->output_path;
        /** @var ContentServiceProvider $content */
        $content = $this->getApp()->content;

        /** @var StaticBuilder $builder */
        $builder = $this->getApp()->builder;
        $this->info("Starting Build", true);
        $this->info("Cleaning up output dir...");
        $builder->cleanUp();

        //Build content single pages
        $contentTypes = $content->getContentTypes();
        /** @var ContentType $contentType */
        foreach ($contentTypes as $contentType) {
            $this->info("Building content type '$contentType->slug'");
            $builder->buildContentType($contentType);
        }

        $this->info("Building tag pages");
        $tags = $content->fetchTagList();
        foreach ($tags as $tag => $articles) {
            $this->info("Building $tag pages...");
            $builder->buildPaginatedTagPage(trim($tag));
        }

        $this->info("Building index");
        $builder->buildPaginatedIndex();

        $this->info("Copying Resources");
        $builder->copyPublicResources();

        $this->info("Building RSS feed");
        $builder->buildRssFeed();

        $this->success("Finished building static website at $outputDir.");
    }
}
