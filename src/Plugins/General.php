<?php

namespace Rocky\FileManager\Plugins;

use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\HttpFoundation\Response;

class General
{
    /**
     * @return Response
     */
    public function list()
    {
        $dirs  = finder()->depth(0)->directories()->in(request_path());
        $files = finder()->depth(0)->files()->in(request_path());

        $list = [
            'dirs'  => [],
            'files' => [],
        ];
        /** @var SplFileInfo $dir */
        foreach ($dirs as $dir) {
            $list['dirs'][] = getFileInfo($dir);
        }
        /** @var SplFileInfo $file */
        foreach ($files as $file) {
            $list['files'][] = getFileInfo($file);
        }

        return jsonResponse($list);
    }

    /**
     * @return Response|null
     */
    public function new_dir()
    {
        $new_path = sanitizePath(request_path().'/'.request('dirname'));
        filesystem()->mkdir($new_path);
        if (filesystem()->exists($new_path)) {
            return jsonResponse(['message' => 'Directory created']);
        }

        return jsonResponse(['message' => 'Could not create new directory'], 500);
    }

    /**
     * @return Response|null
     */
    public function new_file()
    {
        $new_file = sanitizePath(request_path().'/'.request('filename'));
        $content  = request('content');

        filesystem()->appendToFile($new_file, $content);

        return jsonResponse(['message' => 'File saved.']);
    }

    /**
     * @return Response|null
     */
    public function copy()
    {
        return $this->performCopyOperation();
    }

    /**
     * @return Response|null
     */
    public function move()
    {
        return $this->performCopyOperation(true);
    }

    /**
     * @return Response|null
     */
    public function delete()
    {
        $target = sanitizePath(request_path().'/'.request('target'));
        if ( ! filesystem()->exists($target)) {
            return jsonResponse(['message' => 'target does not exist'], 403);
        }
        $this->recursive_delete($target);

        return jsonResponse(['message' => 'Delete successful.']);
    }

    /**
     * @param  bool  $move
     *
     * @return Response|null
     */
    private function performCopyOperation($move = false)
    {
        $source      = sanitizePath(request_path().'/'.request('source'));
        $destination = sanitizePath(request_path().'/'.request('destination'));

        if ( ! filesystem()->exists($source)) {
            return jsonResponse(['message' => 'Source does not exist'], 403);
        }

        if (filesystem()->exists($destination)) {
            return jsonResponse(['message' => 'Destination already exists'], 403);
        }

        if (is_file($source)) {
            filesystem()->copy($source, $destination);
            if ($move) {
                filesystem()->remove($source);
            }
        } else {
            $this->recursive_copy($source, $destination);
            if ($move) {
                $this->recursive_delete($source);
            }
        }

        if (filesystem()->exists($destination)) {
            return jsonResponse(['message' => $move ? 'Moved!' : 'Copied!']);
        }

        return jsonResponse(['message' => $move ? 'Could not move.' : 'Could not copy.'], 500);
    }

    /**
     * @param $source
     * @param $destination
     */
    private function recursive_copy($source, $destination)
    {
        $files = finder()->files()->in($source);
        /** @var SplFileInfo $file */
        foreach ($files as $file) {
            filesystem()->copy($file->getRealPath(), $destination.'/'.$file->getRelativePathname());
        }

        $dirs = finder()->directories()->in($source);
        /** @var SplFileInfo $dir */
        foreach ($dirs as $dir) {
            $path = $destination.'/'.$dir->getRelativePathname();
            if ( ! filesystem()->exists($path)) {
                filesystem()->mkdir($path);
            }
        }
    }

    /**
     * @param $target
     *
     * @return void
     */
    private function recursive_delete($target)
    {
        $files = finder()->files()->in($target);
        /** @var SplFileInfo $file */
        foreach ($files as $file) {
            filesystem()->remove($file->getRealPath());
        }

        $dirs  = finder()->directories()->in($target);
        $_dirs = [];
        /** @var SplFileInfo $dir */
        foreach ($dirs as $dir) {
            $_dirs[] = $dir->getRealPath();
        }
        $_dirs = array_reverse($_dirs);
        foreach ($_dirs as $dir) {
            filesystem()->remove($dir);
        }

        filesystem()->remove($target);
    }
}
