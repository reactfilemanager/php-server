<?php

namespace Rocky\FileManager;

use Psr\Cache\InvalidArgumentException;
use SplFileInfo;
use Symfony\Component\ErrorHandler\Debug;
use Symfony\Component\ErrorHandler\ErrorHandler;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class FileManager
{
    /**
     * FileManager constructor.
     * Enable the error handler
     */
    public function __construct()
    {
        Debug::enable();
        ErrorHandler::register();
    }

    /**
     * @return Response
     * @throws InvalidArgumentException
     */
    private function _sendThumb()
    {
        $thumbFile = request('thumb');
        $file      = base_path($thumbFile);

        $thumb     = null;
        if ( ! $file) {
            $thumb = new SplFileInfo(__DIR__.'/thumbs/404.png');
        } else {
            preventJailBreak($file);

            $thumb = getThumb($file);
            if ( ! $thumb) {
                $thumb = new SplFileInfo(__DIR__.'/thumbs/file.png');
            }
        }

        $response = new BinaryFileResponse($thumb->getRealPath());

        return $this->_send($response);
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
            $response = $this->_sendThumb();

            return $this->_send($response);
        }

        // secure the path
        preventJailBreak();

        // look up the requested plugin and it's action(method)
        $plugin = request('plugin');
        $action = request('action');
        if ( ! filesystem()->exists(__DIR__.'/Plugins/'.$plugin.'.php')) {
            // plugin does not exist
            $response = response()->setStatusCode(403);

            return $this->_send($response);
        }

        $class = '\Rocky\FileManager\Plugins\\'.$plugin;
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
