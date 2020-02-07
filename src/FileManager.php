<?php

namespace Rocky\FileManager;

use Psr\Cache\InvalidArgumentException;
use SplFileInfo;
use Symfony\Component\ErrorHandler\Debug;
use Symfony\Component\ErrorHandler\ErrorHandler;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

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
        if($thumb->getExtension()==='svg') {
            $response->headers->set('Content-Type', 'image/svg+xml'); // MACOS workaround
        }

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
     * Download a file
     *
     * @return Response|null
     */
    private function _downloadFile()
    {
        $thumbFile = request('download');
        $file      = base_path($thumbFile);
        if(!$file || !is_file($file)) {
            return abort(404);
        }
        preventJailBreak($file);

        $file = new BinaryFileResponse($file);
        $file->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT);

        return $this->_send($file);
    }

    /**
     * @return Response|null
     */
    private function _preview()
    {
        $thumbFile = request('preview');
        $file      = base_path($thumbFile);
        if(!$file || !is_file($file)) {
            return abort(404);
        }
        preventJailBreak($file);

        $file = new BinaryFileResponse($file);
        $file->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE);

        return $this->_send($file);
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
            return $this->_sendThumb();
        }

        // look for download request
        if(request('download')) {
            return $this->_downloadFile();
        }

        // look for preview request
        if(request('preview')) {
            return  $this->_preview();
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
