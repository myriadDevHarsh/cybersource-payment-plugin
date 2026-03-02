<?php

namespace scr\core;

use scr\interface\PageInterface;
use scr\interface\TemplateLoaderInterface;

class TemplateLoader implements TemplateLoaderInterface
{
    public function init(PageInterface $page)
    {
        $this->templates = wp_parse_args(
            [ 'page.php', 'index.php' ],
            (array) $page->getTemplate()
        );
    }

    public function load()
    {
        do_action('template_redirect');
        $template = $this->templates[0];
        if (file_exists($template)) {
            status_header(200);
            require $template;
            exit;
        }
    }
}
