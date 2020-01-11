<?php

namespace Rocky\FileManager;

use Symfony\Component\ErrorHandler\Debug;
use Symfony\Component\ErrorHandler\ErrorHandler;
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
     *
     */
    private function preventJailBreak()
    {
        $path = base_path(request('path'));
        if ( ! $path) {
            abort(403);
        }

        $root = realpath(config('root'));
        // the path MUST start with the root
        if ( ! startsWith($path, $root)) {
            abort(403);
        }
    }

    /**
     * Run the app
     *
     * @return Response
     */
    public function run()
    {
        $this->preventJailBreak();

        // look up the requested plugin and it's action(method)
        $plugin = request('plugin');
        $action = request('action');
        if ( ! filesystem()->exists(__DIR__.'/Plugins/'.$plugin.'.php')) {
            // plugin does not exist
            $response = response()->setStatusCode(403);

            return $this->send($response);
        }

        $class = '\Rocky\FileManager\Plugins\\'.$plugin;
        if ( ! class_exists($class)) {
            // class not found
            $response = response()->setStatusCode(403);

            return $this->send($response);
        }

        $instance = new $class();

        if ( ! method_exists($instance, $action)) {
            // action not found
            $response = response()->setStatusCode(403);

            return $this->send($response);
        }

        /** @var Response $response */
        $response = $instance->{$action}();

        return $this->send($response);
    }

    /**
     * @param  Response  $response
     *
     * @return Response
     */
    private function send(Response $response)
    {
        if ($response) {
            return $response->prepare(request())->send();
        }
    }
}
