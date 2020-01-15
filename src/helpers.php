<?php

use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mime\MimeTypes;
use Symfony\Contracts\Cache\ItemInterface;

$container = [];

/**
 * @param  string  $path
 *
 * @return string
 */
function base_path($path = null)
{
    return absolutePath(config('root'), $path);
}

/**
 * @return string
 */
function request_path()
{
    return base_path(request('path'));
}

/**
 * @param  mixed  ...$parts
 *
 * @return false|string
 */
function absolutePath(...$parts)
{
    return realpath(sanitizePath(implode(DIRECTORY_SEPARATOR, $parts)));
}

/**
 * @param $path
 *
 * @return string|string[]|null
 */
function sanitizePath($path)
{
    return preg_replace('(/+)', '/', $path);
}

/**
 * @param  string  $key
 *
 * @return Request|mixed
 */
function request($key = null)
{
    global $container;
    if ( ! isset($container['request'])) {
        $container['request'] = Request::createFromGlobals();
    }

    if ($key !== null) {
        return $container['request']->get($key);
    }

    return $container['request'];
}

/**
 * @param  bool  $flush
 *
 * @return Response|null
 */
function response($flush = false)
{
    global $container;
    if ( ! isset($container['response'])) {
        if ($flush) {
            return null;
        }
        $container['response'] = new Response();
    }

    return $container['response'];
}

/**
 * @param $array
 *
 * @param  int  $code
 *
 * @return Response|null
 */
function jsonResponse($array, $code = 200)
{
    $response = response()->setStatusCode($code);
    $response->setContent(json_encode($array));
    $response->headers->set('Content-Type', 'application/json');

    return $response;
}

/**
 * @return MimeTypes
 */
function mimeTypes()
{
    global $container;
    if ( ! isset($container['mime_types'])) {
        $container['mime_types'] = new MimeTypes();
    }

    return $container['mime_types'];
}

/**
 * @return Finder
 */
function finder()
{
    return new Finder();
}

/**
 * @return Filesystem
 */
function filesystem()
{
    global $container;
    if ( ! isset($container['filesystem'])) {
        $container['filesystem'] = new Filesystem();
    }

    return $container['filesystem'];
}

/**
 * @return FilesystemAdapter
 */
function cache()
{
    global $container;
    if ( ! isset($container['cache'])) {
        $container['cache'] = new FilesystemAdapter('_thumb_cache', 0, config('cache'));
    }

    return $container['cache'];
}

/**
 * @param $path
 * @param  mixed  $value
 *
 * @return mixed|null
 */
function config($path, $value = null)
{
    global $container;
    if ( ! isset($container['config'])) {
        $container['config'] = include __DIR__.'/config.php';
    }
    if ( ! $value) {
        return getConfig($path);
    } else {
        return setConfig($path, $value);
    }
}

/**
 * @param $path
 *
 * @return null
 */
function getConfig($path)
{
    global $container;
    $cf    = $container['config'];
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
 */
function setConfig($path, $value)
{
    global $container;
    $cf    = &$container['config'];
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
 */
function startsWith($haystack, $needle)
{
    $length = strlen($needle);

    return (substr($haystack, 0, $length) === $needle);
}

/**
 * @param $haystack
 * @param $needle
 *
 * @return bool
 */
function endsWith($haystack, $needle)
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
 */
function abort($code, $data = ['message' => 'Aborted'])
{
    $response = jsonResponse($data);
    $response->setStatusCode($code);
    $response->prepare(request())->send();
    die;
}

/**
 * @param  \Symfony\Component\Finder\SplFileInfo  $file
 *
 * @return array
 */
function getFileInfo(\Symfony\Component\Finder\SplFileInfo $file)
{
    $path = request('path');

    return [
        'name'          => $file->getFilename(),
        'path'          => sanitizePath($path.'/'.$file->getRelativePathname()),
        'is_dir'        => $file->isDir(),
        'is_file'       => $file->isFile(),
        'is_link'       => $file->isLink(),
        'is_readable'   => $file->isReadable(),
        'is_writable'   => $file->isWritable(),
        'is_executable' => $file->isExecutable(),
        'size'          => $file->getSize(),
        'extension'     => $file->getExtension(),
    ];
}

/**
 * @param $name
 * @param  string  $ext
 *
 * @return string|string[]|null
 */
function getSafePath($name, $ext = '')
{
    $filepath = sanitizePath(request_path().'/'.$name);
    if ($ext !== '') {
        $filepath .= '.'.$ext;
    }
    $i = 1;
    while (filesystem()->exists($filepath)) {
        $filepath = sanitizePath(request_path().'/'.$name.'('.($i++).')');
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
 */
function ensureSafeFile($filepath)
{
    $mime  = mimeTypes()->guessMimeType($filepath);
    $valid = false;
    foreach (config('uploads.allowed_types') as $allowed_type) {
        if (preg_match("#^{$allowed_type}$#", $mime)) {
            $valid = true;
            break;
        }
    }

    if ( ! $valid) {
        filesystem()->remove($filepath);

        abort(403, ['message' => 'This type of file is not allowed to be downloaded or uploaded']);
    }

    return $mime;
}

/**
 * @param $path
 *
 * @return string|false
 * @throws \Psr\Cache\InvalidArgumentException
 */
function getThumb($path)
{
    $thumbDir = __DIR__.'/thumbs/';
    $thumbExt = '.png';

    $file  = new SplFileInfo($path);
    $thumb = null;

    if ($file->isDir()) {
        $thumb = $thumbDir.'folder'.$thumbExt;
    } elseif ($file->isLink()) {
        $thumb = $thumbDir.'symlink'.$thumbExt;
    } else {
        $thumbImage = cache()->get(md5_file($file->getRealPath()), function (ItemInterface $_) use ($file) {

            return genThumb($file);
        });

        $thumb = tempnam("/tmp", $file->getFilename());

        $handle = fopen($thumb, "w");
        fwrite($handle, $thumbImage);
        fclose($handle);
    }

    return new SplFileInfo($thumb);
}

/**
 * @param $filepath
 *
 * @throws \Psr\Cache\InvalidArgumentException
 */
function deleteThumb($filepath)
{
    cache()->delete(md5_file($filepath));
}

/**
 * @param  SplFileInfo  $file
 *
 * @return false|string
 */
function genThumb(SplFileInfo $file)
{
    $ext = $file->getExtension();
    if ( ! in_array($ext, ['gif', 'jpg', 'png', 'jpeg', 'webp'])) {
        return false;
    }

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
    $virtual_image  = imagecreatetruecolor($desired_width, $desired_height);
    imagesavealpha($virtual_image, true);
    $trans_colour = imagecolorallocatealpha($virtual_image, 0, 0, 0, 127);
    imagefill($virtual_image, 0, 0, $trans_colour);
    imagecopyresized($virtual_image, $resource, 0, 0, 0, 0, $desired_width, $desired_height, $width, $height);
    ob_start();
    imagepng($virtual_image);
    $thumbImage = ob_get_contents();
    ob_end_clean();

    return $thumbImage;
}