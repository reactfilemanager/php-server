<?php

namespace Rocky\FileManager\Plugins;

use Psr\Cache\InvalidArgumentException;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;
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
    public function update()
    {
        $filepath = absolutePath(request_path(), request('target'));
        if ( ! $filepath) {
            return jsonResponse(['message' => 'Requested file does not exist'], 404);
        }

        $content = request('content');
        filesystem()->dumpFile($filepath, $content);

        return jsonResponse(['message' => 'File updated']);
    }

    /**
     * @return Response|null
     */
    public function rename()
    {
        $from = absolutePath(request_path(), request('from'));
        if ( ! $from) {
            return jsonResponse(['message' => 'File/folder does not exist'], 404);
        }
        $to = sanitizePath(request_path().'/'.request('to'));

        filesystem()->rename($from, $to);

        if ( ! filesystem()->exists($to)) {
            return jsonResponse(['message' => 'Could not rename'], 500);
        }

        return jsonResponse(['message' => 'Rename successful']);
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
     * @throws InvalidArgumentException
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
                deleteThumb($source);
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
     * @throws InvalidArgumentException
     */
    private function recursive_delete($target)
    {
        $files = finder()->files()->in($target);
        /** @var SplFileInfo $file */
        foreach ($files as $file) {
            deleteThumb($file->getRealPath());
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
            deleteThumb($dir->getRealPath());
            filesystem()->remove($dir);
        }

        deleteThumb($target);
        filesystem()->remove($target);
    }

    /**
     * @return Response|null
     */
    public function upload()
    {
        /** @var UploadedFile $file */
        $file = request()->files->get('file');
        $file->move(request_path(), $file->getClientOriginalName());

        $filepath = absolutePath(request_path(), $file->getClientOriginalName());

        if (filesystem()->exists($filepath)) {
            ensureSafeFile($filepath);

            return jsonResponse(['message' => 'File upload successful']);
        }

        return jsonResponse(['message' => 'Could not move uploaded file'], 500);
    }

    /**
     * @return Response|null
     */
    public function remote_download()
    {
        $url  = request('url');
        $name = pathinfo($url, PATHINFO_FILENAME);
        $ext  = pathinfo($url, PATHINFO_EXTENSION);

        $filepath = getSafePath($name, $ext);

        filesystem()->copy($url, $filepath);

        if ( ! filesystem()->exists($filepath)) {
            return jsonResponse(['message' => 'Could not download remote file'], 500);
        }

        $mime     = ensureSafeFile($filepath);
        $ext      = mimeTypes()->getExtensions($mime)[0];
        $new_path = getSafePath($name, $ext);
        filesystem()->rename($filepath, $new_path);

        $relative_path = substr($new_path, strlen(base_path()));

        return jsonResponse(['message' => 'The file has been downloaded', 'file' => $relative_path]);
    }
}
