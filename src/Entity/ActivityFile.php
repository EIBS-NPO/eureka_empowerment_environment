<?php

namespace App\Entity;

use App\Repository\ActivityFileRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Entity(repositoryClass=ActivityFileRepository::class)
 */
class ActivityFile extends Activity
{

    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     * @Assert\Type(type="numeric", message=" id is not valid")
     */
    protected  $id;

    /**
     * @ORM\Column(type="string", length=13)
     */
    private $uniqId;

    /**
     * @ORM\Column(type="string", length=50)
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

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $filename;

    public function serialize(String $context = null): array
    {
        $data = Parent::serialize($context);
        $data = array_merge($data, [
            "filename" => $this->filename,
            "fileType" => $this->fileType,
            "size" => $this->size,
        ]);
        return $data;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @param mixed $id
     */
    public function setId($id): void
    {
        $this->id = $id;
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

    public function setForActivity(Activity $activity){
       // $this->setId($activity->getId());
        $this->isPublic = $activity->getIsPublic();
        $this->title = $activity->getTitle();
        $this->summary = $activity->getSummary();
        $this->postDate = $activity->getPostDate();
        $this->picturePath = $activity->getPicturePath();
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

    public function getFilename(): ?string
    {
        return $this->filename;
    }

    public function setFilename(string $filename): self
    {
        $this->filename = $filename;

        return $this;
    }

    public function getUniqId(): ?string
    {
        return $this->uniqId;
    }

    public function setUniqId(string $uniqId): self
    {
        $this->uniqId = $uniqId;

        return $this;
    }
}
