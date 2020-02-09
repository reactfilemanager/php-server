<?php

namespace Rocky\FileManager;

use Psr\Cache\InvalidArgumentException;
use SplFileInfo;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

class FileLoader
{
    /**
     * @return Response
     * @throws InvalidArgumentException
     */
    public static function getThumb()
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

        return $response;
    }

    /**
     * @return BinaryFileResponse|null
     */
    public static function getPreview()
    {

        $file = request('preview');
        $file      = base_path($file);
        if(!$file || !is_file($file)) {
            return abort(404);
        }
        preventJailBreak($file);

        $file = new SplFileInfo($file);

        $response = new BinaryFileResponse($file->getRealPath());
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE);
        if($file->getExtension()==='svg') {
            $response->headers->set('Content-Type', 'image/svg+xml'); // MACOS workaround
        }

        return $response;
    }

    /**
     * @return string|BinaryFileResponse|null
     */
    public static function downloadFile()
    {
        $thumbFile = request('download');
        $file      = base_path($thumbFile);
        if(!$file || !is_file($file)) {
            return abort(404);
        }
        preventJailBreak($file);

        $file = new BinaryFileResponse($file);
        $file->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT);

        return $file;
    }
}