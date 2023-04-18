<?php

namespace librarianphp\Build;

use Librarian\Builder\StaticBuilder;
use Librarian\Provider\ContentServiceProvider;
use Minicli\Command\CommandController;

class DefaultController extends CommandController
{
    public function handle(): void
    {
        $outputDir = $this->getApp()->config->output_path;
        /** @var ContentServiceProvider $content */
        $content = $this->getApp()->content;

        /** @var StaticBuilder $builder */
        $builder = $this->getApp()->builder;
        $this->getPrinter()->info("Starting Build", 1);
        $this->getPrinter()->info("Cleaning up output dir...");
        $builder->cleanUp();

        //Build content single pages
        $contentTypes = $content->getContentTypes();
        foreach ($contentTypes as $contentType) {
            $this->getPrinter()->info("Building content type '$contentType'");
            $builder->buildContentType($contentType);
        }

        $this->getPrinter()->info("Building tag pages");
        $tags = $content->fetchTagList();
        foreach ($tags as $tag => $articles) {
            $this->getPrinter()->info("Building $tag pages...");
            $builder->buildPaginatedTagPage(trim($tag));
        }

        $this->getPrinter()->info("Building index");
        $builder->buildPaginatedIndex();

        $this->getPrinter()->info("Copying Resources");
        $builder->copyPublicResources();

        $this->getPrinter()->info("Building RSS feed");
        $builder->buildRssFeed();

        $this->getPrinter()->success("Finished building static website at $outputDir.");
    }
}
