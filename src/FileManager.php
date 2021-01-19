<?php

namespace ThemeXpert\FileManager;

use Psr\Cache\InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

class FileManager
{
    public static $CONFIG = [];

    /**
     * FileManager constructor.
     * Enable the error handler
     *
     * @param  array  $fm_config
     *
     * @since 1.0.0
     */
    public function __construct($fm_config = [])
    {
        static::$CONFIG = $fm_config;
        $this->_init();
    }

    /**
     * @since 1.0.0
     */
    private function _init()
    {
        $this->_initPlugins();
    }

    /**
     * @since 1.0.0
     */
    private function _initPlugins()
    {
        $plugins = fm_config('plugins');
        foreach ($plugins as $plugin) {
            if (method_exists($plugin, 'init')) {
                $plugin::init();
            }
        }
    }

    /**
     * @param  Response  $fm_response
     *
     * @return Response
     * @since 1.0.0
     */
    private function _send(Response $fm_response)
    {
        if ($fm_response) {
            return $fm_response->prepare(fm_request())->send();
        }
    }

    /**
     * Run the app
     *
     * @return Response
     * @throws InvalidArgumentException
     * @since 1.0.0
     */
    public function run()
    {
        // look for thumb fm_request
        if (fm_request('thumb')) {
            return $this->_send(FileLoader::fm_getThumb());
        }

        // look for download fm_request
        if (fm_request('download')) {
            return $this->_send(FileLoader::downloadFile());
        }

        // look for preview fm_request
        if (fm_request('preview')) {
            return $this->_send(FileLoader::getPreview());
        }

        // secure the path
        fm_preventJailBreak();

        // look up the fm_requested plugin and it's action(method)
        $plugin  = fm_request('plugin');
        $action  = fm_request('action');
        $plugins = fm_config('plugins');
        if ( ! array_key_exists($plugin, $plugins)) {
            // plugin does not exist
            $fm_response = fm_response()->setStatusCode(403);

            return $this->_send($fm_response);
        }

        $class = $plugins[$plugin];
        if ( ! class_exists($class)) {
            // class not found
            $fm_response = fm_response()->setStatusCode(403);

            return $this->_send($fm_response);
        }

        $instance = new $class();

        if ( ! method_exists($instance, $action)) {
            // action not found
            $fm_response = fm_response()->setStatusCode(403);

            return $this->_send($fm_response);
        }

        /** @var Response $fm_response */
        $fm_response = $instance->{$action}();

        return $this->_send($fm_response);
    }
}
