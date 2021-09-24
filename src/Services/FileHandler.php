<?php

namespace App\Services;

use App\Entity\Interfaces\PictorialObject;
use App\Exceptions\BadMediaFileException;
use App\Services\Configuration\ConfigurationHandler;
use Exception;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\Exception\NoFileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * Class FileHandler
 * @package App\Services
 */
class FileHandler
{
    const FILE_DIR = '/files/Activity';

    private String $targetDirectory;
    private SluggerInterface $slugger;

    /**
     * @var ConfigurationHandler ConfigurationHandler object to manipulate the configuration and retrieve configuration keys easily.
     */
    private ConfigurationHandler $configHandler;

    private ?array $allowedMime;
    private LogService $logger;

    /**
     * FileHandler constructor.
     * @param $targetDirectory
     * @param SluggerInterface $slugger
     * @param ConfigurationHandler $configurationHandler
     * @param LogService $logger
     */
    public function __construct($targetDirectory , SluggerInterface $slugger, ConfigurationHandler $configurationHandler, LogService $logger)
    {
        $this->targetDirectory = $targetDirectory;
        $this->slugger = $slugger;
        $this->configHandler = $configurationHandler;
        $this->logger = $logger;
        $this->allowedMime = explode(",",$this->configHandler->getValue("mime.type.allowed")[0]);
    }

    /**
     * @param PictorialObject $object
     * @param $pictureDir
     * @param UploadedFile|null $pictureFile
     * @return PictorialObject
     * @throws BadMediaFileException | FileException
     */
    public function uploadPicture(PictorialObject $object, $pictureDir, ?UploadedFile $pictureFile): PictorialObject
    {
        $oldPicture = $object->getPicturePath();

        //if pictureFile is not null handle the new pictureFile
        if($pictureFile !== null){
            $this->isAllowedMime($pictureFile);
            //make unique picturePath
            $uniqueFilename = $this->makeUniqueFilename($pictureFile);

            //upload
            try{
                $pictureFile->move($this->targetDirectory.$pictureDir, $uniqueFilename);
                chmod($this->targetDirectory.$pictureDir ."/". $uniqueFilename, 0644);
            }catch (Exception | FileException $e) {
                $this->logger->logError($e,null,"error" );
                throw new FileException("Failed: an error occurred while uploading the picture file", 500);
            }

            $object->setPicturePath($uniqueFilename);
        }else{ // else remove the oldPicturePath
            $object->setPicturePath(null);
        }

        //if a picture already exist, need to remove it
        if($oldPicture !== null){
            $this->removeFile($pictureDir.'/'.$oldPicture);
        }

        return $object;
    }

    /**
     * @param String $uniqName
     * @param UploadedFile $file
     * @return void
     * @throws Exception
     */
    public function upload(String $uniqName, UploadedFile $file) :void
    {
        try {
            $file->move($this->targetDirectory.self::FILE_DIR, $uniqName);
            chmod($this->targetDirectory.self::FILE_DIR ."/". $uniqName, 0644);
        } catch (FileException $e) {
            throw new Exception("Failed: an error occurred while uploading the file", 500);
        }

    }

    /**
     * @param $filename
     * @return string
     */
    public function getFile($filename): string
    {

        $fileDir = $this->targetDirectory.self::FILE_DIR."/".$filename;
        if (!file_exists($fileDir)){
            throw new NoFileException("File not found");
        }
        return $fileDir;
    }

    /**
     * @param $entity
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
            return "File not found ";//todo throw exception
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
     * @param $filename
     * @return String
     */
    public function getChecksum($filename) :String {
        return hash_file('sha256', $this->targetDirectory.self::FILE_DIR."/".$filename);
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
     * @throws BadMediaFileException
     */
    public function isAllowedMime(UploadedFile $file) :void{
    //    dd($file->getMimeType());
        if(!(array_search($file->getMimeType(), $this->allowedMime) !== false)){
            $mimeTabText = explode("/", $file->getMimeType());
            $msg = $mimeTabText[0] . " " . $mimeTabText[1] . " not allowed";
            throw new BadMediaFileException($msg);
        }
    }

    public function makeUniqueFilename($file): string
    {
        return uniqid().'_'. $this->getOriginalFilename($file).'.'. $file->guessExtension();
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