<?php

namespace App\Service;

use Exception;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;

//todo ajout des gestion de config pour les limite fichier
//s'inspirer de RequestSecurity
class PictureHandler
{
    private String $targetDirectory;
    private SluggerInterface $slugger;

    public function __construct($targetDirectory , SluggerInterface $slugger)
    {
        $this->targetDirectory = $targetDirectory;
        $this->slugger = $slugger;
    }

    /**
     * just short className like "User", or "Organization" to direct the correct files's folder
     * @param $className
     * @return String
     */
    public function getFileDir($className) :String {
        return $this->targetDirectory.'/'.$className .'/';
    }

    public function upload($className, UploadedFile $file)
    {
        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = $this->slugger->slug($originalFilename);
        $fileName = $safeFilename.'-'.uniqid().'.'.$file->guessExtension();

        try {
            $file->move($this->getFileDir($className), $fileName);
        } catch (FileException $e) {
            throw new Exception("Failed: an error occurred while uploading the file", 500);
        }

        return $fileName;
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
}