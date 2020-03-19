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
        \ThemeXpert\FileManager\add_filter('core@list', function ($list) {
            $list['files'] = array_map(function (array $file) {
                $ext = $file['extension'];
                if ($ext === 'jpg' || $ext === 'jpeg') {
                    $file['extra'] = array_merge(
                        $file['extra'],
                        ['pjpeg' => static::isInterlaced(base_path($file['path']))]
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
            $handle   = fopen($filename, "r");
            $contents = fread($handle, 32);
            fclose($handle);

            return (ord($contents[28]) != 0);
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
            $filepath = absolutePath(request_path(), request('filepath'));
            $im       = imagecreatefromjpeg($filepath);
            imageinterlace($im, true);
            imagejpeg($im, $filepath, 100);

            return jsonResponse(['message' => 'Converted']);
        } catch (Exception $e) {
            return  jsonResponse($e->getMessage(), 500);
        }
    }
}