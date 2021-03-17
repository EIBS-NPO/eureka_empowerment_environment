<?php

namespace App\Service;

use App\Service\Configuration\ConfigurationHandler;
use Exception;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\Exception\NoFileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * Class FileHandler
 * @package App\Service
 */
class FileHandler
{
    private String $targetDirectory;
    private SluggerInterface $slugger;

    /**
     * @var ConfigurationHandler ConfigurationHandler object to manipulate the configuration and retrieve configuration keys easily.
     */
    private ConfigurationHandler $configHandler;

    private ?array $allowedMime;

    /**
     * FileHandler constructor.
     * @param $targetDirectory
     * @param SluggerInterface $slugger
     * @param ConfigurationHandler $configurationHandler
     */
    public function __construct($targetDirectory , SluggerInterface $slugger, ConfigurationHandler $configurationHandler)
    {
        $this->targetDirectory = $targetDirectory;
        $this->slugger = $slugger;
        $this->configHandler = $configurationHandler;
        $this->allowedMime = $this->configHandler->getValue("mime.type.allowed");
    }

    /**
     * @param String $fileDir
     * @param String $uniqName
     * @param UploadedFile $file
     * @return void
     * @throws Exception
     */
    public function upload(String $fileDir, String $uniqName, UploadedFile $file) :void
    {
        try {
            $file->move($this->targetDirectory.$fileDir, $uniqName);
            chmod($this->targetDirectory.$fileDir ."/". $uniqName, 0644);
        } catch (FileException $e) {
            throw new Exception("Failed: an error occurred while uploading the file", 500);
        }

    }

    /**
     * @param $filePath
     * @return string
     */
    public function getFile($filePath){
        $fileDir = $this->targetDirectory.$filePath;
        if (!file_exists($fileDir)){
            throw new NoFileException("File not found");
        }
        return $fileDir;
    }

    /**
     * @param $entity
     * @param $className
     * @return mixed
     */
    public function loadPicture($entity) {
        $className = $this->getClassName($entity);
        if($className === "ActivityFile"){ $className = "Activity";}
        $fileDir = '/pictures/'.$className.'/'.$entity->getPicturePath();

        if($entity->getPicturePath() !== null){
                $img = $this->getPic($fileDir);
                $entity->setPictureFile($img);
            }
        return $entity;
    }

    /**
     * @param $filePath
     * @return string
     */
    public function getPic($filePath)
    {

        $fileDir = $this->targetDirectory.$filePath;

        if (file_exists($fileDir)){
            return base64_encode(file_get_contents($fileDir));
        }
        else {
            return "File not found ";
        }
    }

    /**
     * @param $filePath
     */
    public function removeFile($filePath){
        $fileDir = $this->targetDirectory.$filePath;
        if (file_exists($fileDir)){
                unlink( $fileDir );
        }
    }

    /**
     * @param $filePath
     * @return String
     */
    public function getChecksum($filePath) :String {
        return hash_file('sha256', $this->targetDirectory.$filePath);
    }

    /**
     * throw an Exception if the control checksum don't match
     * @param String $filePath
     * @param String $bddCheckSum
     * @return bool
     */
    public function controlChecksum(String $filePath, String $bddCheckSum) : bool{
        return hash_equals($this->getChecksum($filePath), $bddCheckSum);
    }

    /**
     * throw an Exception if the file's mime isn't in allowedMime
     * @param UploadedFile $file
     * @throws Exception
     */
    public function isAllowedMime(UploadedFile $file) :void{
        if(!(array_search($file->getMimeType(), $this->allowedMime) !== false)){
            $msg = $file->getMimeType() . "not allowed";
            throw new Exception($msg ,415);
        }
    }

    /**
     * @param UploadedFile $file
     * @return String
     */
    public function getOriginalFilename(UploadedFile $file) : String {
        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        return $this->slugger->slug($originalFilename);
    }

    public function getClassName($entity) :String {
        $namespace = explode("\\", get_class($entity));
        return end($namespace);
    }
}