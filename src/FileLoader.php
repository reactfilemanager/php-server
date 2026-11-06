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
        $etag      = null;
        if ( ! $file) {
            $thumb = new SplFileInfo(__DIR__.'/thumbs/404.png');
        } else {
            fm_preventJailBreak($file);

            // Thumbnails are requested again on every open of the file
            // manager, and each request boots the whole CMS. Answer a repeat
            // from the browser cache: the key changes when the file does.
            $etag = '"'.fm_thumbKey($file).'"';
            $ifNoneMatch = fm_request()->headers->get('If-None-Match');
            if ($ifNoneMatch !== null && trim($ifNoneMatch) === $etag) {
                $notModified = new Response('', 304);
                self::cacheHeaders($notModified, $etag);

                return $notModified;
            }

            $thumb = fm_getThumb($file);
            if ( ! $thumb) {
                $thumb = new SplFileInfo(__DIR__.'/thumbs/file.png');
            }
        }

        /**
         * Added getPathname checker to avoid false in windows
         * getRealPath returns false on windows on some server.
         * getPathname returns the path
         * @since 1.4.4 Jul 21 2022
         */
        $realPath = $thumb->getRealPath() ? $thumb->getRealPath() : $thumb->getPathname();
        $fm_response = new BinaryFileResponse($realPath);

        // A generated thumbnail is a temp file of ours: remove it once sent.
        // Never the original (SVGs are served as themselves).
        $tempDir = realpath(fm_config('cache') ?: sys_get_temp_dir());
        if (strpos($thumb->getFilename(), 'fmthumb_') === 0
            && $tempDir !== false
            && realpath($thumb->getPath()) === $tempDir) {
            $fm_response->deleteFileAfterSend(true);
        }

        self::hardenHeaders($fm_response, $thumb->getExtension());

        if ($etag !== null) {
            self::cacheHeaders($fm_response, $etag);
        }

        return $fm_response;
    }

    /**
     * Let the browser keep a thumbnail for a while and revalidate by ETag.
     * Private: the URL carries the user's session token.
     *
     * @param  Response  $response
     * @param  string    $etag
     */
    private static function cacheHeaders($response, $etag)
    {
        $response->headers->set('Cache-Control', 'private, max-age=3600');
        $response->headers->set('ETag', $etag);
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

        self::hardenHeaders($fm_response, $file->getExtension());

        return $fm_response;
    }

    /**
     * Apply anti-XSS headers to a served file. `nosniff` stops content-type
     * confusion; SVGs (served inline as image/svg+xml) additionally get a
     * sandbox CSP so any embedded script cannot execute even for files that
     * were uploaded before server-side SVG sanitisation existed.
     *
     * @param  Response  $response
     * @param  string    $extension
     *
     * @return void
     */
    private static function hardenHeaders($response, $extension)
    {
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        if (strtolower((string) $extension) === 'svg') {
            $response->headers->set('Content-Type', 'image/svg+xml'); // MACOS workaround
            $response->headers->set(
                'Content-Security-Policy',
                "default-src 'none'; style-src 'unsafe-inline'; sandbox"
            );
        }
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
