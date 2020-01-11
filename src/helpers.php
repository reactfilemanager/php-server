<?php

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

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
    return preg_replace('(\/+)', '/', $path);
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
 * @param $key
 * @param  mixed  $value
 *
 * @return mixed|null
 */
function config($key, $value = null)
{
    global $container;
    if ( ! isset($container['config'])) {
        $container['config'] = include __DIR__.'/config.php';
    }
    if ( ! $value) {
        if (isset($container['config'][$key])) {
            return $container['config'][$key];
        } else {
            return null;
        }
    } else {
        return $container['config'][$key] = $value;
    }
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
 */
function abort($code)
{
    $response = jsonResponse(['message' => 'Aborted']);
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
        'name'       => $file->getFilename(),
        'path'       => sanitizePath($path.'/'.$file->getRelativePathname()),
        'is_dir'     => $file->isDir(),
        'is_file'    => $file->isFile(),
        'is_link'    => $file->isLink(),
        'readable'   => $file->isReadable(),
        'writable'   => $file->isWritable(),
        'executable' => $file->isExecutable(),
        'size'       => $file->getSize(),
        'extension'  => $file->getExtension(),
        'type'       => $file->getType(),
    ];
}