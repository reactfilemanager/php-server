<?php

namespace Rocky\FileManager;

use Psr\Cache\InvalidArgumentException;
use Symfony\Component\ErrorHandler\Debug;
use Symfony\Component\ErrorHandler\ErrorHandler;
use Symfony\Component\HttpFoundation\Response;

class FileManager
{
    public static $CONFIG = [];

    /**
     * FileManager constructor.
     * Enable the error handler
     *
     * @param  array  $config
     */
    public function __construct($config = [])
    {
        static::$CONFIG = $config;
        $this->_init();
    }

    /**
     *
     */
    private function _init()
    {
        Debug::enable();
        ErrorHandler::register();
        $this->_initPlugins();
    }

    /**
     *
     */
    private function _initPlugins()
    {
        $plugins = config('plugins');
        foreach ($plugins as $plugin) {
            if (method_exists($plugin, 'init')) {
                $plugin::init();
            }
        }
    }

    /**
     * @param  Response  $response
     *
     * @return Response
     */
    private function _send(Response $response)
    {
        if ($response) {
            return $response->prepare(request())->send();
        }
    }

    /**
     * Run the app
     *
     * @return Response
     * @throws InvalidArgumentException
     */
    public function run()
    {
        // look for thumb request
        if (request('thumb')) {
            return $this->_send(FileLoader::getThumb());
        }

        // look for download request
        if (request('download')) {
            return $this->_send(FileLoader::downloadFile());
        }

        // look for preview request
        if (request('preview')) {
            return $this->_send(FileLoader::getPreview());
        }

        // secure the path
        preventJailBreak();

        // look up the requested plugin and it's action(method)
        $plugin  = request('plugin');
        $action  = request('action');
        $plugins = config('plugins');
        if ( ! array_key_exists($plugin, $plugins)) {
            // plugin does not exist
            $response = response()->setStatusCode(403);

            return $this->_send($response);
        }

        $class = $plugins[$plugin];
        if ( ! class_exists($class)) {
            // class not found
            $response = response()->setStatusCode(403);

            return $this->_send($response);
        }

        $instance = new $class();

        if ( ! method_exists($instance, $action)) {
            // action not found
            $response = response()->setStatusCode(403);

            return $this->_send($response);
        }

        /** @var Response $response */
        $response = $instance->{$action}();

        return $this->_send($response);
    }
}
