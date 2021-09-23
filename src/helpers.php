<?php

use ThemeXpert\FileManager\FileManager;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mime\MimeTypes;
use Symfony\Contracts\Cache\ItemInterface;

$fmcontainer = [];
$actions   = [];
$filters   = [];

/**
 * @param $hook
 * @param $callable
 *
 * @since 1.0.0
 */
function fm_add_action($hook, $callable)
{
    global $actions;
    if ( ! isset($actions[$hook])) {
        $actions[$hook] = [];
    }
    $actions[$hook][] = $callable;
}

/**
 * @param $hook
 * @param $value
 *
 * @return mixed
 * @since 1.0.0
 */
function fm_do_action($hook, $value)
{
    global $actions;

    return array_reduce($actions[$hook], function ($value, $action) {
        return $action($value);
    }, $value);
}

/**
 * @param $hook
 * @param $callable
 *
 * @since 1.0.0
 */
function fm_add_filter($hook, $callable)
{
    global $filters;
    if ( ! isset($filters[$hook])) {
        $filters[$hook] = [];
    }
    $filters[$hook][] = $callable;
}

/**
 * @param $hook
 * @param $value
 *
 * @return mixed
 * @since 1.0.0
 */
function fm_apply_filter($hook, $value)
{
    global $filters;
    if($filters && $filters[$hook] !== null){
        return array_reduce($filters[$hook], function ($value, $filter) {
            return $filter($value);
        }, $value);
    }

    return $value;
}

/**
 * @param  string  $path
 *
 * @return string
 * @since 1.0.0
 */
function fm_base_path($path = null)
{
    return fm_absolutePath(fm_config('root'), $path);
}

/**
 * @return string
 * @since 1.0.0
 */
function fm_request_path()
{
    return fm_base_path(fm_request('path'));
}

/**
 * @param  mixed  ...$parts
 *
 * @return false|string
 * @since 1.0.0
 */
function fm_absolutePath(...$parts)
{
    return realpath(fm_sanitizePath(implode(DIRECTORY_SEPARATOR, $parts)));
}

/**
 * @param $path
 *
 * @return string|string[]|null
 * @since 1.0.0
 */
function fm_sanitizePath($path)
{
    return preg_replace('(/+)', '/', $path);
}

/**
 * @param  string  $path
 *
 * @since 1.0.0
 */
function fm_preventJailBreak($path = null)
{
    if ($path === false) {
        return;
    }

    $path = $path ? $path : fm_base_path(fm_request('path'));
    if ( ! $path) {
        fm_abort(403, ['message' => 'Invalid fm_request']);
    }

    $root = realpath(fm_config('root'));
    // the path MUST start with the root
    if ( ! fm_startsWith($path, $root)) {
        fm_abort(403, ['message' => 'Jailbreak detected']);
    }
}

/**
 * @param  string  $key
 *
 * @return Request|mixed
 * @since 1.0.0
 */
function fm_request($key = null)
{
    global $fmcontainer;
    if ( ! isset($fmcontainer['fm_request'])) {
        $fmcontainer['fm_request'] = Request::createFromGlobals();
    }

    if ($key !== null) {
        return $fmcontainer['fm_request']->get($key);
    }

    return $fmcontainer['fm_request'];
}

/**
 * @param  bool  $flush
 *
 * @return Response|null
 * @since 1.0.0
 */
function fm_response($flush = false)
{
    global $fmcontainer;
    if ( ! isset($fmcontainer['fm_response'])) {
        if ($flush) {
            return null;
        }
        $fmcontainer['fm_response'] = new Response();
    }

    return $fmcontainer['fm_response'];
}

/**
 * @param $array
 *
 * @param  int  $code
 *
 * @return Response|null
 * @since 1.0.0
 */
function fm_jsonResponse($array, $code = 200)
{
    $fm_response = fm_response()->setStatusCode($code);
    $fm_response->setContent(json_encode(fm_utf8_converter($array)));
    $fm_response->headers->set('Content-Type', 'application/json');

    return $fm_response;
}

function fm_utf8_converter($array)
{
    array_walk_recursive($array, function (&$item, $key) {
        if (!mb_detect_encoding($item, 'utf-8', true)) {
            $item = utf8_encode($item);
        }
    });

    return $array;
}

/**
 * @return MimeTypes
 * @since 1.0.0
 */
function fm_mimeTypes()
{
    global $fmcontainer;
    if ( ! isset($fmcontainer['mime_types'])) {
        $fmcontainer['mime_types'] = new MimeTypes();
    }

    return $fmcontainer['mime_types'];
}

/**
 * @return Finder
 * @since 1.0.0
 */
function fm_finder()
{
    return new Finder();
}

/**
 * @return Filesystem
 * @since 1.0.0
 */
function fm_filesystem()
{
    global $fmcontainer;
    if ( ! isset($fmcontainer['fm_filesystem'])) {
        $fmcontainer['fm_filesystem'] = new Filesystem();
    }

    return $fmcontainer['fm_filesystem'];
}

/**
 * @return FilesystemAdapter
 * @since 1.0.0
 */
function fm_cache()
{
    global $fmcontainer;
    if ( ! isset($fmcontainer['fm_cache'])) {
        $fmcontainer['fm_cache'] = new FilesystemAdapter('_thumb_fm_cache', 0, fm_config('fm_cache'));
    }

    return $fmcontainer['fm_cache'];
}

/**
 * @param $path
 * @param  mixed  $value
 *
 * @return mixed|null
 * @since 1.0.0
 */
function fm_config($path, $value = null)
{
    global $fmcontainer;
    if ( ! isset($fmcontainer['fm_config'])) {
        $fmcontainer['fm_config'] = fm_deepMerge(include __DIR__.'/config.php', FileManager::$CONFIG);
    }
    if ( ! $value) {
        return fm_getConfig($path);
    } else {
        return fm_setConfig($path, $value);
    }
}

/**
 * @param $array1
 * @param $array2
 *
 * @return mixed
 * @since 1.0.0
 */
function fm_deepMerge($array1, $array2)
{
    foreach ($array2 as $key => $value) {
        if ( ! isset($array1[$key])) {
            $array1[$key] = $value;
            continue;
        }

        $_value = $array1[$key];
        if (gettype($_value) !== 'array') {
            $array1[$key] = $value;
        } else {
            $array1[$key] = fm_deepMerge($_value, $value);
        }
    }

    return $array1;
}

/**
 * @param $path
 *
 * @return null
 * @since 1.0.0
 */
function fm_getConfig($path)
{
    global $fmcontainer;
    $cf    = $fmcontainer['fm_config'];
    $_path = explode('.', $path);
    foreach ($_path as $_p) {
        if (isset($cf[$_p])) {
            $cf = $cf[$_p];
        } else {
            return null;
        }
    }

    return $cf;
}

/**
 * @param $path
 * @param $value
 *
 * @return bool
 * @since 1.0.0
 */
function fm_setConfig($path, $value)
{
    global $fmcontainer;
    $cf    = &$fmcontainer['fm_config'];
    $_path = explode('.', $path);
    $last  = array_pop($_path);
    foreach ($_path as $_p) {
        if (isset($cf[$_p])) {
            $cf = &$cf[$_p];
        } else {
            return false;
        }
    }
    $cf[$last] = $value;

    return true;
}

/**
 * @param $haystack
 * @param $needle
 *
 * @return bool
 * @since 1.0.0
 */
function fm_startsWith($haystack, $needle)
{
    $length = strlen($needle);

    return (substr($haystack, 0, $length) === $needle);
}

/**
 * @param $haystack
 * @param $needle
 *
 * @return bool
 * @since 1.0.0
 */
function fm_endsWith($haystack, $needle)
{
    $length = strlen($needle);
    if ($length == 0) {
        return true;
    }

    return (substr($haystack, -$length) === $needle);
}

/**
 * @param $code
 * @param  array  $data
 *
 * @return null
 * @since 1.0.0
 */
function fm_abort($code, $data = ['message' => 'Aborted'])
{
    $fm_response = fm_jsonResponse($data);
    $fm_response->setStatusCode($code);
    $fm_response->prepare(fm_request())->send();
    die;
}

/**
 * @param  \Symfony\Component\Finder\SplFileInfo  $file
 * @param  string  $type
 *
 * @return array
 * @since 1.0.0
 */
function fm_getFileInfo(\Symfony\Component\Finder\SplFileInfo $file, $type = 'dirs')
{
    $path = fm_request('path');

    $info = [
        'name'          => $file->getFilename(),
        'path'          => fm_sanitizePath($path.'/'.$file->getRelativePathname()),
        'is_dir'        => $file->isDir(),
        'is_file'       => $file->isFile(),
        'is_link'       => $file->isLink(),
        'is_readable'   => $file->isReadable(),
        'is_writable'   => $file->isWritable(),
        'is_executable' => $file->isExecutable(),
        'perms'         => fm_getFilePerms($file->getRealPath()),
        'size'          => $file->getSize(),
        'extension'     => strtolower($file->getExtension()),
        'last_modified' => $file->getMTime(),
        'extra'         => [],
    ];

    /**
     * previously was $file->isFile()
     * now added as params
     * @since 1.3.0
     */
    if ($type === 'files') {
        $mime = fm_mimeTypes()->guessMimeType($file->getRealPath());
        if (preg_match('#^image/#', $mime)) {
            $dimension = getimagesize($file->getRealPath());
            if ($info) {
                $info['image_info'] = [
                    'width'    => @$dimension['0'],
                    'height'   => @$dimension['1'],
                    'bits'     => @$dimension['bits'],
                    'channels' => @$dimension['channels'],
                    'mime'     => @$dimension['mime'],
                ];
            }
        }
    }

    return $info;
}

/**
 * @param $name
 * @param  string  $ext
 *
 * @return string|string[]|null
 * @since 1.0.0
 */
function fm_getSafePath($name, $ext = '')
{
    $filepath = fm_sanitizePath(fm_request_path().'/'.$name);
    if ($ext !== '') {
        $filepath .= '.'.$ext;
    }
    $i = 1;
    while (fm_filesystem()->exists($filepath)) {
        $filepath = fm_sanitizePath(fm_request_path().'/'.$name.'('.($i++).')');
        if ($ext !== '') {
            $filepath .= '.'.$ext;
        }
    }

    return $filepath;
}

/**
 * @param $filepath
 *
 * @return string|Response|null
 * @since 1.0.0
 */
function fm_ensureSafeFile($filepath)
{
    $mime = fm_mimeTypes()->guessMimeType($filepath);
    if (fm_config('uploads.mime_check')) {
        $valid = false;
        foreach (fm_config('uploads.allowed_types') as $allowed_type) {
            if (preg_match("#^{$allowed_type}$#", $mime)) {
                $valid = true;
                break;
            }
        }

        if ( ! $valid) {
            fm_filesystem()->remove($filepath);

            fm_abort(403, ['message' => 'File type not allowed']);
        }
    }

    return $mime;
}

/**
 * @param $path
 *
 * @return string|false
 * @throws \Psr\Cache\InvalidArgumentException
 * @since 1.0.0
 */
function fm_getThumb($path)
{
    $thumbDir = __DIR__.'/thumbs/';
    $thumbExt = '.png';

    $file  = new SplFileInfo($path);
    $thumb = null;
    $ext   = strtolower($file->getExtension());

    if ($file->isDir()) {
        $thumb = $thumbDir.'folder'.$thumbExt;
    } elseif ($file->isLink()) {
        $thumb = $thumbDir.'symlink'.$thumbExt;
    } else {
        if ($ext === 'svg') {
            return $file;
        } elseif (in_array($ext, ['gif', 'jpg', 'png', 'jpeg', 'webp'])) {
            $thumbImage = fm_cache()->get(md5_file($file->getRealPath()), function (ItemInterface $_) use ($file) {
                return fm_genThumb($file);
            });

            $thumb = tempnam(fm_config('cache'), $file->getFilename());

            $handle = fopen($thumb, 'w');
            fwrite($handle, $thumbImage);
            fclose($handle);
        } elseif (fm_filesystem()->exists($thumbDir.$ext.$thumbExt)) {
            $thumb = $thumbDir.$ext.$thumbExt;
        } else {
            return null;
        }
    }

    return new SplFileInfo($thumb);
}

/**
 * @param $filepath
 *
 * @throws \Psr\Cache\InvalidArgumentException
 * @since 1.0.0
 */
function fm_deleteThumb($filepath)
{
    fm_cache()->delete(md5_file($filepath));
}

/**
 * @param  SplFileInfo  $file
 *
 * @return false|string
 * @since 1.0.0
 */
function fm_genThumb(SplFileInfo $file)
{
    $ext = strtolower($file->getExtension());

    $path     = $file->getRealPath();
    $resource = null;

    if ($ext == 'gif') {
        $resource = imagecreatefromgif($path);
    } elseif ($ext == 'png') {
        $resource = imagecreatefrompng($path);
    } elseif ($ext == 'jpg' || $ext == 'jpeg') {
        $resource = imagecreatefromjpeg($path);
    } elseif ($ext == 'webp') {
        $resource = imagecreatefromwebp($path);
    }
    $width          = imagesx($resource);
    $height         = imagesy($resource);
    $desired_height = 150;
    $desired_width  = floor($width * ($desired_height / $height));
    $virtual_image  = imagecreatetruecolor($desired_width ?: 5, $desired_height);
    imagesavealpha($virtual_image, true);
    $trans_colour = imagecolorallocatealpha($virtual_image, 0, 0, 0, 127);

    if ($ext == 'png') {
        // removing the black from the placeholder
        imagecolortransparent($virtual_image, $trans_colour);

        // turning off alpha blending (to ensure alpha channel information
        // is preserved, rather than removed (blending with the rest of the
        // image in the form of black))
        imagealphablending($virtual_image, false);
    } else {
        imagefill($virtual_image, 0, 0, $trans_colour);
    }
    // resize
    imagecopyresized($virtual_image, $resource, 0, 0, 0, 0, $desired_width, $desired_height, $width, $height);

    ob_start();
    imageinterlace($virtual_image, true);

    if ($ext == 'png') {
        imagepng($virtual_image, null, 9);
    } else {
        imagejpeg($virtual_image, null, 100);
    }

    $thumbImage = ob_get_contents();
    ob_end_clean();

    return $thumbImage;
}

/**
 * Retrieves the file permission
 *
 * @param $file
 *
 * @return string
 * @since 1.0.0
 */
function fm_getFilePerms($file)
{
    $perms = fileperms($file);

    switch ($perms & 0xF000) {
        case 0xC000: // socket
            $info = 's';
            break;
        case 0xA000: // symbolic link
            $info = 'l';
            break;
        case 0x8000: // regular
            $info = 'r';
            break;
        case 0x6000: // block special
            $info = 'b';
            break;
        case 0x4000: // directory
            $info = 'd';
            break;
        case 0x2000: // character special
            $info = 'c';
            break;
        case 0x1000: // FIFO pipe
            $info = 'p';
            break;
        default: // unknown
            $info = 'u';
    }

    // Owner
    $info .= (($perms & 0x0100) ? 'r' : '-');
    $info .= (($perms & 0x0080) ? 'w' : '-');
    $info .= (($perms & 0x0040) ?
        (($perms & 0x0800) ? 's' : 'x') :
        (($perms & 0x0800) ? 'S' : '-'));

    // Group
    $info .= (($perms & 0x0020) ? 'r' : '-');
    $info .= (($perms & 0x0010) ? 'w' : '-');
    $info .= (($perms & 0x0008) ?
        (($perms & 0x0400) ? 's' : 'x') :
        (($perms & 0x0400) ? 'S' : '-'));

    // World
    $info .= (($perms & 0x0004) ? 'r' : '-');
    $info .= (($perms & 0x0002) ? 'w' : '-');
    $info .= (($perms & 0x0001) ?
        (($perms & 0x0200) ? 't' : 'x') :
        (($perms & 0x0200) ? 'T' : '-'));

    return $info;
}
