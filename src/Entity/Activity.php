<?php

namespace App\Entity;

use App\Repository\ActivityRepository;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\InheritanceType;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Entity(repositoryClass=ActivityRepository::class)
 * @InheritanceType("JOINED")
 */
class Activity
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     * @Assert\Type(type="numeric", message=" id is not valid")
     */
    private ?int $id;

    /**
     * @ORM\Column(type="boolean")
     * @Assert\NotBlank(message="isPublic is required")
     * @Assert\Type(type="bool", message=" isPublic not valid boolean")
     */
    protected ?bool $isPublic;

    /**
     * @ORM\Column(type="string", length=50)
     * @Assert\NotBlank(message="the title is required")
     * @Assert\Type(type="string", message=" title is not valid string")
     * @Assert\Length(min="2", max="50",
     *     minMessage="the title must be at least 2 characters long",
     *     maxMessage="the title must not exceed 50 characters")
     */
    protected ?string $title;

    /**
     * @ORM\Column(type="string", length=255)
     * @Assert\NotBlank(message="the description is required")
     * @Assert\Type(type="string", message=" description is not valid string")
     * @Assert\Length(min="2", max="255",
     *     minMessage="the description must be at least 2 characters long",
     *     maxMessage="the description must not exceed 255 characters")
     */
    protected ?string $description;

    //todo add timezone
    /**
     * @ORM\Column(type="date")
     * @Assert\NotBlank(message="the postDate is required")
     * @Assert\Type(type={"DateTime", "Y-m-d"}, message= "the date must be in the format YYYY-mm-dd")
     * @Assert\GreaterThanOrEqual("today", message="post date must be today or greater date")
     */
    protected ?\DateTimeInterface $postDate;

    /**
     * @ORM\Column(type="string", length=255)
     * @Assert\Type(type="string", message=" summary is not valid string")
     */
    protected ?string $summary;

    /**
     * @ORM\ManyToOne(targetEntity=User::class, inversedBy="activities")
     * @ORM\JoinColumn(nullable=false)
     * @Assert\NotBlank(message="the creator is required")
     * @Assert\Type(type={"App\Entity\User", "integer"})
     */
    protected ?User $creator = null;

    /**
     * @ORM\ManyToOne(targetEntity=Project::class, inversedBy="activities")
     * @Assert\Type(type={"App\Entity\Project", "integer"})
     */
    protected ?Project $project = null;

    /**
     * @ORM\ManyToOne(targetEntity=Organization::class, inversedBy="activities")
     * @Assert\Type(type={"App\Entity\Organization", "integer"})
     */
    protected ?Organization $organization = null;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private ?string $picturePath;

    /**
     * base64_encode(picture)
     */
    private $pictureFile;

    public function serialize(String $context = null): array
    {
        $data = [
            "id" => $this->id,
            "title" => $this->title,
            "description" => $this->description,
            "summary" => $this->summary,
            "postDate" => $this->postDate->format('Y-m-d'),
            "isPublic" => $this->isPublic,
            "creator" => $this->creator->serialize()
        ];

        //Check some attributes to see if they are sets
        if($this->pictureFile){
            $data["picture"] = $this->pictureFile;
        }
        if($this->project){
            $data["project"] = $this->project->getId();
        }
        if($this->organization){
            $data["organization"] = $this->organization->getId();
        }

        return $data;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getIsPublic(): ?bool
    {
        return $this->isPublic;
    }

    public function setIsPublic(bool $isPublic): self
    {
        $this->isPublic = $isPublic;

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getPostDate(): ?\DateTimeInterface
    {
        return $this->postDate;
    }

    public function setPostDate(\DateTimeInterface $postDate): self
    {
        $this->postDate = $postDate;

        return $this;
    }

    public function getSummary(): ?string
    {
        return $this->summary;
    }

    public function setSummary(string $summary): self
    {
        $this->summary = $summary;

        return $this;
    }

    public function getCreator(): ?User
    {
        return $this->creator;
    }

    public function setCreator(?User $creator): self
    {
        $this->creator = $creator;

        return $this;
    }

    public function getProject(): ?Project
    {
        return $this->project;
    }

    public function setProject(?Project $project): self
    {
        $this->project = $project;

        return $this;
    }

    public function getOrganization(): ?Organization
    {
        return $this->organization;
    }

    public function setOrganization(?Organization $organization): self
    {
        $this->organization = $organization;

        return $this;
    }

    public function getPicturePath(): ?string
    {
        return $this->picturePath;
    }

    public function setPicturePath(?string $picturePath): self
    {
        $this->picturePath = $picturePath;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getPictureFile()
    {
        return $this->pictureFile;
    }

    /**
     * @param mixed $pictureFile
     */
    public function setPictureFile($pictureFile): void
    {
        $this->pictureFile = $pictureFile;
    }

}
