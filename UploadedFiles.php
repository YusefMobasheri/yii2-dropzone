<?php
namespace devgroup\dropzone;

use app\components\Imager;
use app\models\Attachment;
use Yii;
use yii\helpers\ArrayHelper;
use yii\helpers\Json;

class UploadedFiles
{
    private $_path;
    private $_pathUrl;
    private $_storedFiles = [];
    private $_options = [];
    /* @var $_imager Imager */
    private $_imager;

    public function __construct($path, $files = false, $options = [])
    {
        $this->_options = $options;
        $this->setPath($path);
        if($files)
            $this->addFiles($files);
    }

    /**
     * set upload path directory
     *
     * @param $path
     */
    public function setPath($path)
    {
        $this->_path = Yii::getAlias('@webroot/' . $path . '/');
        $this->_pathUrl = Yii::getAlias('@web/' . $path . '/');

        if(!is_dir($this->_path))
            mkdir($this->_path, 0777, true);

        if($this->getOption('thumbnail'))
            $this->createThumbPath();
    }

    /**
     * @return string path
     */
    public function getPath()
    {
        return $this->_path;
    }

    /**
     * Returns path directory's url
     * @return string url
     */
    public function getBaseUrl()
    {
        return $this->_pathUrl;
    }

    /**
     * Add Uploaded File Structure to stored files array
     *
     * @param string|Attachment $filename without base path
     */
    public function add($filename, $filePath = false)
    {
        $path = $this->_path;
        $url = $this->_pathUrl;
        if($filePath){
            $path = $this->normalizePath($filePath);
            $url = $this->normalizeUrl($filePath);
        }
        if((string)$filename && is_file($path . $filename))
            $this->_storedFiles[] = [
                'name' => $filename,
                'src' => $url . $filename,
                'size' => filesize($path . $filename),
                'serverName' => $filename,
            ];
    }

    /**
     * @param $filename
     * @param bool $deleteFile
     * @return bool
     */
    public function remove($filename, $deleteFile = false)
    {
        $fl = false;
        $sf = $this->getFiles();
        if($sf)
            foreach($sf as $k => $f)
                if($f && isset($f['serverName']) && $f['serverName'] == $filename){
                    if($deleteFile){
                        @unlink($this->_path . $filename);
                        if($this->getOption('thumbnail'))
                            @unlink($this->getThumbPath() . $filename);
                    }
                    unset($this->_storedFiles[$k]);
                    $fl = true;
                }
        return $fl;
    }

    public function removeAll($deleteFile = false)
    {
        $fl = false;
        $sf = $this->getFiles();
        if($sf)
            foreach($sf as $k => $f)
                if($f && isset($f['serverName']) && file_exists($this->_path . $f['serverName'])){
                    $filename = $f['serverName'];
                    if($deleteFile){
                        @unlink($this->_path . $filename);
                        if($this->getOption('thumbnail'))
                            @unlink($this->getThumbPath() . $filename);
                    }
                    unset($this->_storedFiles[$k]);
                    $fl = true;
                }
        return $fl;
    }

    /**
     * @param $filename
     * @return bool
     */
    public function exists($filename)
    {
        $fl = false;
        $sf = $this->getFiles();
        if($sf)
            foreach($sf as $k => $f)
                if($f && isset($f['serverName']) && $f['serverName'] == $filename)
                    $fl = $k;
        return $fl;
    }

    /**
     * Replace old file in stored array with new filename
     *
     * @param string $oldFilename
     * @param string $newFilename
     * @param bool $deleteFile
     */
    public function replace($oldFilename, $newFilename, $deleteFile = true)
    {
        $this->remove($oldFilename, $deleteFile);
        $this->add($newFilename);
    }

    /**
     * Update files List
     * 
     * @param $oldFilename
     * @param $newFilename
     * @return string|null
     */
    public function update($oldFilename, $newFilename, $newFilePath, $isArray = false)
    {
        if($isArray){
            if(!is_array($newFilename))
                $newFilename = Json::decode($newFilename);
            if($oldFilename)
                foreach($oldFilename as $key => $filename){
                    $nKey = array_search($filename, $newFilename);
                    if($nKey === false)
                        $this->remove($filename, true);
                    else
                        unset($newFilename[$nKey]);
                }
            if($newFilename)
                foreach($newFilename as $filename){
                    $this->saveFile($this->normalizePath($newFilePath) . $filename, $this->getPath() . $filename);
                    $this->add($filename);
                }
        }else{
            if($oldFilename != $newFilename){
                $this->saveFile($this->normalizePath($newFilePath) . $newFilename, $this->getPath() . $newFilename);
                $this->replace($oldFilename, $newFilename);
            }
        }
    }

    private function saveFile($oldFilename, $newFilename, $thumbPath = false)
    {
        if(is_file($oldFilename)){
            // resize
            if($this->getOption('resize')){
                $flag = @rename($oldFilename, $newFilename);
                $this->doResize($newFilename);
            }else
                $flag = @rename($oldFilename, $newFilename);
            // create thumbnail
            if($flag && $this->getOption('thumbnail'))
                $this->createThumbnail($newFilename, ($thumbPath?$thumbPath:$this->getThumbPath()) . basename($oldFilename));
        }
    }

    /**
     * Add Several Files in one place to stored files array
     * @param array $files
     */
    public function addFiles($files = [])
    {
        if($files){
            if(is_array($files))
                foreach($files as $file)
                    $this->add($file);
            else
                $this->add($files);
        }
    }

    /**
     * Returns Stored Files structured array
     * @return array
     */
    public function getFiles()
    {
        return $this->_storedFiles;
    }

    public function move($destinationPath, $fileName = false)
    {
        if($this->getFiles()){
            if($fileName)
                $this->moveFile($destinationPath, $fileName);
            else
                foreach($this->getFiles() as $file)
                    $this->moveFile($destinationPath, $file['serverName']);
        }
    }

    public function moveFile($destinationPath, $fileName)
    {
        if(is_file($this->_path . $fileName))
            $this->saveFile($this->_path . $fileName, $this->normalizePath($destinationPath) . $fileName, $this->getThumbPath($destinationPath));
        $index = $this->exists($fileName);
        if($index !== false)
            unset($this->_storedFiles[$index]);
    }

    public function normalizePath($path)
    {
        if (!is_dir(Yii::getAlias('@webroot') . DIRECTORY_SEPARATOR . $path))
            @mkdir(Yii::getAlias('@webroot') . DIRECTORY_SEPARATOR . $path, 0777, true);
        return Yii::getAlias('@webroot') . DIRECTORY_SEPARATOR . $path . DIRECTORY_SEPARATOR;
    }

    public function normalizeUrl($path)
    {
        return Yii::$app->getRequest()->getBaseUrl() . '/' . $path . '/';
    }

    public function getOption($name)
    {
        return isset($this->_options[$name])?$this->_options[$name]:null;
    }

    public function getThumbPath($newPath = false)
    {
        $w = isset($this->getOption('thumbnail')['width']) && $this->getOption('thumbnail')['width']?$this->getOption('thumbnail')['width']:150;
        $h = isset($this->getOption('thumbnail')['height']) && $this->getOption('thumbnail')['height']?$this->getOption('thumbnail')['height']:150;
        $path = ($newPath?$this->normalizePath($newPath):$this->_path) . 'thumbs' . DIRECTORY_SEPARATOR . "{$w}x{$h}" . DIRECTORY_SEPARATOR;
        if($newPath)
            @mkdir($path, 0777, true);
        return $path;
    }

    public function createThumbPath()
    {
        @mkdir($this->getThumbPath(), 0777, true);
    }

    public function createThumbnail($image, $destination)
    {

        $w = isset($this->getOption('thumbnail')['width']) && $this->getOption('thumbnail')['width'] ? $this->getOption('thumbnail')['width'] : 150;
        $h = isset($this->getOption('thumbnail')['height']) && $this->getOption('thumbnail')['height'] ? $this->getOption('thumbnail')['height'] : 150;
        $q = isset($this->getOption('thumbnail')['quality']) && $this->getOption('thumbnail')['quality'] ? $this->getOption('thumbnail')['quality'] : 100;
        $this->getImager()->createThumbnail($image, $w, $h, false, $destination, $q);
    }

    public function doResize($image, $destination = false)
    {
        $w = isset($this->getOption('resize')['width']) && $this->getOption('resize')['width']?$this->getOption('resize')['width']:600;
        $h = isset($this->getOption('resize')['height']) && $this->getOption('resize')['height']?$this->getOption('resize')['height']:400;
        $this->getImager()->resize($image, $destination?:$image, $w, $h);
    }

    public function getImager()
    {
        if(!$this->_imager)
            try{
                $this->_imager = new Imager();
            }catch(\Exception $e){
                throw new \Exception("Create new Imager instance error. Imager Class not found.", 500, $e);
            }
        return $this->_imager;
    }
}