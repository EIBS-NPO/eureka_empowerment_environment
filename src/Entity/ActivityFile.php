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

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $checksum;

    private $file;

    /**
     * @ORM\Column(type="integer")
     */
    private $size;

    public function serialize(String $context = null): array
    {
        $data = Parent::serialize();
        $data = array_merge($data, [
            "filePath" => $this->filePath,
            "size" => $this->size,
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

    public function setForActivity($activity){
        $this->id = $activity->getId();
        $this->isPublic = $activity->getIsPublic();
        $this->title = $activity->getTitle();
        $this->summary = $activity->getSummary();
        $this->postDate = $activity->getPostDate();
        $this->creator = $activity->getCreator();
        $this->project = $activity->getProject();
        $this->organization = $activity->getOrganization();
    }

    public function getChecksum(): ?string
    {
        return $this->checksum;
    }

    public function setChecksum(?string $checksum): self
    {
        $this->checksum = $checksum;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getFile()
    {
        return $this->file;
    }

    /**
     * @param mixed $file
     */
    public function setFile($file): void
    {
        $this->file = $file;
    }

    public function getSize(): ?int
    {
        return $this->size;
    }

    public function setSize(int $size): self
    {
        $this->size = $size;

        return $this;
    }

}
