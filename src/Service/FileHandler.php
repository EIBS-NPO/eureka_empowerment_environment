<?php

namespace App\Service;

use App\Service\Configuration\ConfigurationHandler;
use Exception;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\Exception\NoFileException;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;

//todo ajout des gestion de config pour les limite fichier
//s'inspirer de RequestSecurity
class FileHandler
{
    private String $targetDirectory;
    private SluggerInterface $slugger;

    /**
     * @var ConfigurationHandler ConfigurationHandler object to manipulate the configuration and retrieve configuration keys easily.
     */
    private $configHandler;

    private $allowedMime;

    //todo
   // private $pictureAllowedMime;

    public function __construct($targetDirectory , SluggerInterface $slugger, ConfigurationHandler $configurationHandler)
    {
        $this->targetDirectory = $targetDirectory;
        $this->slugger = $slugger;
        $this->configHandler = $configurationHandler;
        $this->allowedMime = $this->configHandler->getConfigTab("mime.type.allowed");
    }

    /**
     * just short className like "User", or "Organization" to direct the correct files's folder
     * @param $className
     * @return String
     */
    public function getFileDir($className) :String {
        if($className === "Activity" || $className === "ActivityFile"){
            return $this->targetDirectory.'/files/Activity/';
        }else {
            return $this->targetDirectory.'/pictures/'. $className .'/';
        }

    }

    public function upload($className, UploadedFile $file)
    {
        //test typeMime is allowed?
        $isAllowed = false;

        foreach ($this->allowedMime as $mime) {
            if ($mime->getPropertyValue() === $file->getMimeType()) {
                $isAllowed = true;
            }
        }
        if(!$isAllowed){
            $tm = $file->getMimeType();
            throw new Exception("Type MIME: $tm isn't allowed",415);
        }


      //  $file->getMimeType();

        //todo check mime limitation config etc... and config jpa
        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = $this->slugger->slug($originalFilename);
        $fileName = $safeFilename.'-'.uniqid().'.'.$file->guessExtension();

        try {
            //todo move with permission use chmod after move
            $file->move($this->getFileDir($className), $fileName);
            chmod($this->getFileDir($className) ."/". $fileName, 0644);

        } catch (FileException $e) {
            throw new Exception("Failed: an error occurred while uploading the file", 500);
        }

        return $fileName;
    }

    public function getFile($className, $file_path){
        $fileDir=$this->getFileDir($className).'/' . $file_path;
        if (!file_exists($fileDir)){
            throw new NoFileException("File not found");
        }
        return $fileDir;
      //  return new File($fileDir);
    }

    public function getPic($className, $file_path)
    {
        $fileDir =  $this->getFileDir($className).'/' . $file_path;
        //   $path2 = $destination .'/'. $this->dataRequest["pic"] .".png";

        //todo pour génériser, reconnaitre filer image et fichier bureau
        if (file_exists($fileDir)){
          //  $img = file_get_contents($file_dir);
            return base64_encode(file_get_contents($fileDir));
        }
        else {
            return "no found data";
        }
    }

    public function removeFile($className, $file_path){
        $fileDir =  $this->getFileDir($className).'/' . $file_path;
        if (file_exists($fileDir)){
            //  $img = file_get_contents($file_dir);
           /* try{*/
                unlink ( $fileDir );
            /*}catch(e Exception){

            }*/
        }
    }

    public function getChecksum(String $className, String $filepath) :String {
        return hash_file('sha256', $this->getFileDir($className).'/' .$filepath);
    }

    public function verifyChecksum(String $className, String $filepath, String $bddCheckSum) : bool{
        return $this->getChecksum($className, $filepath) === $bddCheckSum;
    }
}