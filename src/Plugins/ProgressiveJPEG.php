<?php

namespace ThemeXpert\FileManager\Plugins;

use Exception;
use Symfony\Component\HttpFoundation\Response;

class ProgressiveJPEG
{
    /**
     *
     */
    public static function init()
    {
        fm_add_filter('core@list', function ($list) {
            $list['files'] = array_map(function (array $file) {
                $ext = $file['extension'];
                if ($ext === 'jpg' || $ext === 'jpeg') {
                    $file['extra'] = array_merge(
                        $file['extra'],
                        ['pjpeg' => static::isInterlaced(fm_base_path($file['path']))]
                    );
                }

                return $file;
            }, $list['files']);

            return $list;
        });
    }

    /**
     * @param $filename
     *
     * @return bool
     */
    private static function isInterlaced($filename)
    {
        try {
            $handle = fopen($filename, 'r');
            $contents = fread($handle, 32);
            fclose($handle);

            return isset($contents[28]) && ord($contents[28]) != 0 ? true : false;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * @return Response|null
     */
    public function convert()
    {
        try {
            $filepath = fm_absolutePath(fm_request_path(), fm_request('filepath'));
            $im = imagecreatefromjpeg($filepath);
            imageinterlace($im, true);
            imagejpeg($im, $filepath, 100);

            return fm_jsonResponse(['message' => 'Converted']);
        } catch (Exception $e) {
            return  fm_jsonResponse($e->getMessage(), 500);
        }
    }
}
