<?php

namespace scr\interface;

interface ControllerInterface
{
    /**
     * Init the controller, fires the hook that allows consumer to add pages
     */
    public function init();

    /**
     * Register a page object in the controller
     *
     * @param  \GM\VirtualPages\Page $page
     * @return \GM\VirtualPages\Page
     */
    public function addPage(PageInterface $page);

    /**
     * Run on 'do_parse_request' and if the request is for one of the registered pages
     * setup global variables, fire core hooks, requires page template and exit.
     *
     * @param boolean $bool The boolean flag value passed by 'do_parse_request'
     * @param \WP $wp       The global wp object passed by 'do_parse_request'
     */
    public function dispatch($bool, \WP $wp);
}
