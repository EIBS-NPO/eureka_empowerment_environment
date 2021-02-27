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
    private $configHandler;

    private $allowedMime =[];

    //todo
   // private $pictureAllowedMime;

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
     * just short className like "User", or "Organization" to direct the correct files's folder
     * @param String $fileDir
     * @param String $uniqName
     * @param UploadedFile $file
     * @return void
     * @throws Exception
     */
    public function upload(String $fileDir, String $uniqName, UploadedFile $file) :void
    {
        //todo les pictures prennent du chmod? why not? but comment supprimer les fichier après?
        try {
            //todo move with permission use chmod after move
    //        dd($this->targetDirectory.$fileDir);
            $file->move($this->targetDirectory.$fileDir, $uniqName);
            chmod($this->targetDirectory.$fileDir ."/". $uniqName, 0644);

            //test retour permission file
            //fileperms ( $this->getFileDir($className) ."/". $uniqId);

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
 //       dd($fileDir);
        //return $fileDir;
        //todo download file
     //   return base64_encode(file_get_contents($fileDir));
    //    return new File($fileDir);
        return $fileDir;
    }

    /**
     * @param $filePath
     * @return string
     */
    public function getPic($filePath)
    {
     //   $this->targetDirectory.$fileDir, $uniqName
        $fileDir = $this->targetDirectory.$filePath;
//dd($fileDir);
        //   $path2 = $destination .'/'. $this->dataRequest["pic"] .".png";
        //todo permission denied, du chmod. :°
        //todo pour génériser, reconnaitre filer image et fichier bureau
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
            //  $img = file_get_contents($file_dir);
           /* try{*/
                unlink( $fileDir );
            /*}catch(e Exception){

            }*/
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
     * throw an Exception is the control checksum don't match
     * @param $filePath
     * @param String $bddCheckSum
     * @return void
     * @throws Exception
     */
    public function controlChecksum($filePath, String $bddCheckSum) : void{
        if(!hash_file('sha256', $this->targetDirectory.$filePath) === $bddCheckSum){
            throw new Exception("checksum COMPARISON FAILED");
        }
    }

    /**
     * throw an Exception if the file's mime isn't in allowedMime
     * @param UploadedFile $file
     * @throws Exception
     */
    public function isAllowedMime(UploadedFile $file) :void{
        if(!array_search($file->getMimeType(), $this->allowedMime)){
            $tm = $file->getMimeType();
            throw new Exception($tm ,415);
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
}