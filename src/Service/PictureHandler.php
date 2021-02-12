<?php

namespace App\Service;

use Exception;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;

class PictureHandler
{
    private String $targetDirectory;
    private SluggerInterface $slugger;

    public function __construct($targetDirectory , SluggerInterface $slugger)
    {
        $this->targetDirectory = $targetDirectory;
        $this->slugger = $slugger;
    }

    public function upload(UploadedFile $file)
    {
        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = $this->slugger->slug($originalFilename);
        $fileName = $safeFilename.'-'.uniqid().'.'.$file->guessExtension();

        try {
            $file->move($this->getTargetDirectory(), $fileName);
        } catch (FileException $e) {
            // ... handle exception if something happens during file upload
        }

        return $fileName;
    }

    public function getPic($file_path)
    {
        $file_dir =  $this->targetDirectory.'/' . $file_path;
        //   $path2 = $destination .'/'. $this->dataRequest["pic"] .".png";

        if (file_exists($file_dir)){
          //  $img = file_get_contents($file_dir);
            return base64_encode(file_get_contents($file_dir));
        }
        else {
            return "no found data";
        }
    }
}