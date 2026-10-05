<?php

namespace {

    use SilverStripe\CMS\Model\SiteTree;

    /**
     * Default page type for this installer. Keep in the global namespace.
     */
    class Page extends SiteTree
    {
        /**
         * @var array<string, string>
         */
        private static $db = [];

        /**
         * @var array<string, string>
         */
        private static $has_one = [];
    }
}
