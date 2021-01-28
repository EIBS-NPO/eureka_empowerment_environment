<?php

namespace App\Entity;

use App\Repository\ActivityFileRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=ActivityFileRepository::class)
 */
class ActivityFile extends Activity
{
     //* @ORM\Id
     //* @ORM\GeneratedValue
     //* @ORM\Column(type="integer")
    /*private $id;*/

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $filePath;

    /**
     * @ORM\Column(type="string", length=10)
     */
    private $fileType;

    /*public function getId(): ?int
    {
        return $this->id;
    }*/

    public function serialize(String $context = null): array
    {
        $data = Parent::serialize();
        $data = array_merge($data, [
            "filePath" => $this->filePath,
            "fileType" => $this->fileType
        ]);
        return $data;
    }

    public function getFilePath(): ?string
    {
        return $this->filePath;
    }

    public function setFilePath(string $filePath): self
    {
        $this->filePath = $filePath;

        return $this;
    }

    public function getFileType(): ?string
    {
        return $this->fileType;
    }

    public function setFileType(string $fileType): self
    {
        $this->fileType = $fileType;

        return $this;
    }
}
