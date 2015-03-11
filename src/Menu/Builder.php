<?php

namespace Bolt\Menu;

use Bolt\Application;
use Bolt\Menu;
use Bolt\Translation\Translator as Trans;
use Bolt\Library as Lib;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;

class Builder
{
    /**
     * @var Application
     */
    private $app;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    public function menu($identifier = null, $resolved = true)
    {
        $menus = $this->app['config']->get('menu');

        if (!empty($identifier) && isset($menus[$identifier])) {
            $menu = $menus[$identifier];
        } else {
            $menu = \utilphp\util::array_first($menus);
        }

        if (!$resolved)
            return $menu;

        return $this->resolve($menu);
    }

    public function resolve(array $menu)
    {
        return $this->menuBuilder($menu);
    }

    /**
     * Recursively scans the passed array to ensure everything gets the menuHelper() treatment.
     *
     * @param array $menu
     *
     * @return array
     */
    private function menuBuilder(array $menu)
    {
        foreach ($menu as $key => $item) {
            $menu[$key] = $this->menuHelper($item);
            if (isset($item['submenu'])) {
                $menu[$key]['submenu'] = $this->menuBuilder($item['submenu']);
            }
        }

        return $menu;
    }

    /**
     * Updates a menu item to have at least a 'link' key.
     *
     * @param array $item
     *
     * @return array Keys 'link' and possibly 'label', 'title' and 'path'
     */
    private function menuHelper($item)
    {
        if (isset($item['submenu']) && is_array($item['submenu'])) {
            $item['submenu'] = $this->menuHelper($item['submenu']);
        }

        if (isset($item['path']) && $item['path'] == "homepage") {
            $item['link'] = $this->app['paths']['root'];
        } elseif (isset($item['route'])) {
            $param = empty($item['param']) ? array() : $item['param'];
            $add = empty($item['add']) ? '' : $item['add'];

            $item['link'] = Lib::path($item['route'], $param, $add);
        } elseif (isset($item['path']) && !isset($item['link'])) {
            if (preg_match('#^(https?://|//)#i', $item['path'])) {
                // We have a mistakenly placed URL, allow it but log it.
                $item['link'] = $item['path'];
                $this->app['logger.system']->error(Trans::__('Invalid menu path (%PATH%) set in menu.yml. Probably should be a link: instead!', array('%PATH%' => $item['path'])), array('event' => 'config'));
            } else {
                // Get a copy of the path minus trainling/leading slash
                $path = ltrim(rtrim($item['path'], '/'), '/');

                // Pre-set our link in case the match() throws an exception
                $item['link'] = '/' . $path;

                try {
                    // See if we have a 'content/id' or 'content/slug' path
                    if (preg_match('#^([a-z0-9_-]+)/([a-z0-9_-]+)$#i', $path)) {

                        // Determine if the provided path first matches any routes
                        // that we have, this will catch any valid configured
                        // contenttype slug and record combination, or throw a
                        // ResourceNotFoundException exception otherwise
                        $this->app['url_matcher']->match('/' . $path);

                        // If we found a valid routing match then we're still here,
                        // attempt to retrive the actual record.
                        $content = $this->app['storage']->getContent($path);
                        if ($content instanceof \Bolt\Content) {
                            if (empty($item['label'])) {
                                $item['label'] = !empty($content->values['title']) ? $content->values['title'] : "";
                            }

                            if (empty($item['title'])) {
                                $item['title'] = !empty($content->values['subtitle']) ? $content->values['subtitle'] : "";
                            }

                            $item['link'] = $content->link();
                        }
                    } else {
                        $item['link'] = '/' . $path;
                    }
                } catch (ResourceNotFoundException $e) {
                    $this->app['logger.system']->error(Trans::__('Invalid menu path (%PATH%) set in menu.yml. Does not match any configured contenttypes or routes.', array('%PATH%' => $item['path'])), array('event' => 'config'));
                }
            }
        }

        return $item;
    }
}
