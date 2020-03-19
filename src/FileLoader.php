<?php

namespace ThemeXpert\FileManager;

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
    public static function fm_getThumb()
    {
        $thumbFile = fm_request('thumb');
        $file      = fm_base_path($thumbFile);

        $thumb     = null;
        if ( ! $file) {
            $thumb = new SplFileInfo(__DIR__.'/thumbs/404.png');
        } else {
            fm_preventJailBreak($file);

            $thumb = fm_getThumb($file);
            if ( ! $thumb) {
                $thumb = new SplFileInfo(__DIR__.'/thumbs/file.png');
            }
        }

        $fm_response = new BinaryFileResponse($thumb->getRealPath());
        if($thumb->getExtension()==='svg') {
            $fm_response->headers->set('Content-Type', 'image/svg+xml'); // MACOS workaround
        }

        return $fm_response;
    }

    /**
     * @return BinaryFileResponse|null
     */
    public static function getPreview()
    {

        $file = fm_request('preview');
        $file      = fm_base_path($file);
        if(!$file || !is_file($file)) {
            return fm_abort(404);
        }
        fm_preventJailBreak($file);

        $file = new SplFileInfo($file);

        $fm_response = new BinaryFileResponse($file->getRealPath());
        $fm_response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE);
        if($file->getExtension()==='svg') {
            $fm_response->headers->set('Content-Type', 'image/svg+xml'); // MACOS workaround
        }

        return $fm_response;
    }

    /**
     * @return string|BinaryFileResponse|null
     */
    public static function downloadFile()
    {
        $thumbFile = fm_request('download');
        $file      = fm_base_path($thumbFile);
        if(!$file || !is_file($file)) {
            return fm_abort(404);
        }
        fm_preventJailBreak($file);

        $file = new BinaryFileResponse($file);
        $file->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT);

        return $file;
    }
}