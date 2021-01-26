<?php

namespace ThemeXpert\FileManager\Plugins;

use Psr\Cache\InvalidArgumentException;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;

class Core
{
    /**
     * @return Response
     */
    public function list()
    {
        $dirs = fm_finder()->depth(0)->directories()->in(fm_request_path());
        $files = fm_finder()->depth(0)->files()->in(fm_request_path());

        // check if there are any search results
        $list = [
            'dirs' => [],
            'files' => [],
        ];

        /** @var SplFileInfo $dir */
        foreach ($dirs as $dir) {
            $list['dirs'][] = fm_getFileInfo($dir);
        }

        /** @var SplFileInfo $file */
        foreach ($files as $file) {
            $list['files'][] = fm_getFileInfo($file, 'files');
        }
        $filtered_list = fm_apply_filter('core@list', $list);

        return fm_jsonResponse($filtered_list);
    }

    /**
     * @return Response|null
     */
    public function new_dir()
    {
        $new_path = fm_sanitizePath(fm_request_path() . '/' . fm_request('dirname'));
        fm_preventJailBreak($new_path);

        if (fm_filesystem()->exists($new_path)) {
            return fm_jsonResponse(['message' => 'Directory exists'], 403);
        }

        fm_filesystem()->mkdir($new_path);
        if (fm_filesystem()->exists($new_path)) {
            return fm_jsonResponse(['message' => 'Directory created']);
        }

        return fm_jsonResponse(['message' => 'Could not create new directory'], 500);
    }

    /**
     * @return Response|null
     */
    public function new_file()
    {
        $new_file = fm_sanitizePath(fm_request_path() . '/' . fm_request('filename'));
        fm_preventJailBreak($new_file);

        if (fm_filesystem()->exists($new_file)) {
            return fm_jsonResponse(['message' => 'File exists']);
        }

        $content = fm_request('content');

        fm_filesystem()->appendToFile($new_file, $content);

        return fm_jsonResponse(['message' => 'File saved.']);
    }

    /**
     * @return Response|null
     */
    public function update()
    {
        $filepath = fm_absolutePath(fm_request_path(), fm_request('target'));
        fm_preventJailBreak($filepath);
        if (!$filepath) {
            return fm_jsonResponse(['message' => 'Requested file does not exist'], 404);
        }

        $content = fm_request('content');
        fm_filesystem()->dumpFile($filepath, $content);

        return fm_jsonResponse(['message' => 'File updated']);
    }

    /**
     * @return Response|null
     */
    public function rename()
    {
        $from = fm_absolutePath(fm_request_path(), fm_request('from'));
        fm_preventJailBreak($from);
        if (!$from) {
            return fm_jsonResponse(['message' => 'File/folder does not exist'], 404);
        }
        $to = fm_sanitizePath(fm_request_path() . '/' . fm_request('to'));
        fm_preventJailBreak($to);
        if (fm_filesystem()->exists($to)) {
            return fm_jsonResponse(['message' => 'A file/folder with the same name exists'], 406);
        }

        fm_filesystem()->rename($from, $to);

        if (!fm_filesystem()->exists($to)) {
            return fm_jsonResponse(['message' => 'Could not rename'], 500);
        }

        return fm_jsonResponse(['message' => 'Rename successful']);
    }

    /**
     * @return Response|null
     * @throws InvalidArgumentException
     */
    public function copy()
    {
        return $this->performCopyOperation();
    }

    /**
     * @return Response|null
     * @throws InvalidArgumentException
     */
    public function move()
    {
        return $this->performCopyOperation(true);
    }

    /**
     * @return Response|null
     */
    public function chmod()
    {
        $target = fm_sanitizePath(fm_request_path() . '/' . fm_request('target'));
        fm_preventJailBreak($target);
        if (!fm_filesystem()->exists($target)) {
            return fm_jsonResponse(['message' => 'target does not exist'], 403);
        }

        $mode = fm_request('mod');
        $mode = str_pad($mode, 3, '0', STR_PAD_LEFT);
        $mode = intval($mode);

        fm_filesystem()->chmod($target, octdec($mode));

        return fm_jsonResponse(['message' => 'File permission has been updated.']);
    }

    /**
     * @return Response|null
     * @throws InvalidArgumentException
     */
    public function delete()
    {
        $target = fm_sanitizePath(fm_request_path() . '/' . fm_request('target'));
        fm_preventJailBreak($target);
        if (!fm_filesystem()->exists($target)) {
            return fm_jsonResponse(['message' => 'target does not exist'], 403);
        }
        if (is_file($target)) {
            fm_deleteThumb($target);
            fm_filesystem()->remove($target);
        } else {
            $this->recursive_delete($target);
        }

        return fm_jsonResponse(['message' => 'Delete successful.']);
    }

    /**
     * @param  bool  $move
     *
     * @return Response|null
     * @throws InvalidArgumentException
     */
    private function performCopyOperation($move = false)
    {
        $source = fm_absolutePath(fm_base_path() . fm_request('source'));
        $destination = fm_absolutePath(fm_base_path() . fm_request('destination'));

        if (!$source || !$destination) {
            return fm_jsonResponse(['message' => 'Invalid fm_request'], 403);
        }

        fm_preventJailBreak($source);
        fm_preventJailBreak($destination);

        if (!fm_filesystem()->exists($source)) {
            return fm_jsonResponse(['message' => 'Source does not exist'], 403);
        }

        $_source = new \SplFileInfo($source);
        $_destination = fm_sanitizePath($destination . '/' . $_source->getFilename());

        if (fm_filesystem()->exists($_destination)) {
            return fm_jsonResponse(['message' => 'Destination already exists'], 403);
        }

        if ($_source->isFile()) {
            fm_filesystem()->copy($source, $_destination);
            if ($move && fm_filesystem()->exists($_destination)) {
                fm_deleteThumb($source);
                fm_filesystem()->remove($source);
            }
        } else {
            $this->recursive_copy($source, $_destination);
            if ($move && fm_filesystem()->exists($_destination)) {
                $this->recursive_delete($source);
            }
        }

        if (fm_filesystem()->exists($_destination)) {
            return fm_jsonResponse(['message' => $move ? 'Moved!' : 'Copied!']);
        }

        return fm_jsonResponse(['message' => $move ? 'Could not move.' : 'Could not copy.'], 500);
    }

    /**
     * @param $source
     * @param $destination
     */
    private function recursive_copy($source, $destination)
    {
        $items = 0;
        $files = fm_finder()->files()->in($source);
        /** @var SplFileInfo $file */
        foreach ($files as $file) {
            $items++;
            fm_filesystem()->copy($file->getRealPath(), $destination . '/' . $file->getRelativePathname());
        }

        $dirs = fm_finder()->directories()->in($source);
        /** @var SplFileInfo $dir */
        foreach ($dirs as $dir) {
            $items++;
            $path = $destination . '/' . $dir->getRelativePathname();
            if (!fm_filesystem()->exists($path)) {
                fm_filesystem()->mkdir($path);
            }
        }

        if ($items === 0) {
            fm_filesystem()->mkdir($destination);
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
        $files = fm_finder()->files()->in($target);
        /** @var SplFileInfo $file */
        foreach ($files as $file) {
            fm_deleteThumb($file->getRealPath());
            fm_filesystem()->remove($file->getRealPath());
        }

        $dirs = fm_finder()->directories()->in($target);
        $_dirs = [];
        /** @var SplFileInfo $dir */
        foreach ($dirs as $dir) {
            $_dirs[] = $dir->getRealPath();
        }
        $_dirs = array_reverse($_dirs);
        foreach ($_dirs as $dir) {
            fm_deleteThumb($dir);
            fm_filesystem()->remove($dir);
        }

        fm_deleteThumb($target);
        fm_filesystem()->remove($target);
    }

    /**
     * @return Response|null
     * @throws InvalidArgumentException
     */
    public function upload()
    {
        /** @var UploadedFile $file */
        $file = fm_request()->files->get('file');

        // ensure if the file is allowed to be uploaded
        fm_ensureSafeFile($file->getRealPath());

        $max_upload_size = fm_config('uploads.max_upload_size');

        if ($max_upload_size) {
            if ($max_upload_size * 1048576 < $file->getSize()) {
                fm_abort(406, ['message' => 'File size must be less than ' . $max_upload_size . 'MB']);
            }
        }

        $filename = fm_absolutePath(fm_request_path(), $file->getClientOriginalName());
        if ($filename) {
            $option = fm_request('fm_option');
            if ($option === 'replace') {
                // replace the existing file
                fm_deleteThumb($filename);
                fm_filesystem()->remove($filename);
                $file->move(fm_request_path(), $file->getClientOriginalName());
            } elseif ($option === 'keep-both') {
                // keep both files
                // save the new file under new name
                $_filename = pathinfo($filename, PATHINFO_FILENAME);
                $_ext = pathinfo($filename, PATHINFO_EXTENSION);
                $name = fm_getSafePath($_filename, $_ext);
                $file->move(fm_request_path(), pathinfo($name, PATHINFO_BASENAME));
            } else {
                // send the message to confirm an option
                // acceptable options [keep-both, replace]
                return fm_jsonResponse(['message' => 'File exists'], 412);
            }
        } else {
            // no existing file, move it
            $file->move(fm_request_path(), $file->getClientOriginalName());
        }

        $filepath = fm_absolutePath(fm_request_path(), $file->getClientOriginalName());

        if (fm_filesystem()->exists($filepath)) {
            return fm_jsonResponse(['message' => 'File upload successful']);
        }

        return fm_jsonResponse(['message' => 'Could not move uploaded file'], 500);
    }

    /**
     * @return Response|null
     */
    public function remote_download()
    {
        $url = fm_request('url');
        $name = pathinfo($url, PATHINFO_FILENAME);
        $ext = pathinfo($url, PATHINFO_EXTENSION);

        $filepath = fm_getSafePath($name, $ext);

        fm_filesystem()->copy($url, $filepath);

        if (!fm_filesystem()->exists($filepath)) {
            return fm_jsonResponse(['message' => 'Could not download remote file'], 500);
        }

        $mime = fm_ensureSafeFile($filepath);
        $ext = fm_mimeTypes()->getExtensions($mime)[0];
        $name = preg_replace('/[^a-zA-Z0-9]+/', '', $name);
        $new_path = fm_getSafePath($name, $ext);
        fm_filesystem()->rename($filepath, $new_path);

        $relative_path = substr($new_path, strlen(fm_base_path()));

        return fm_jsonResponse(['message' => 'The file has been downloaded', 'file' => $relative_path]);
    }
}
