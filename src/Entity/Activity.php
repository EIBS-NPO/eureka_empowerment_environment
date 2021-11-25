<?php

namespace App\Entity;

use App\Entity\Interfaces\PictorialObject;
use App\Repository\ActivityRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\InheritanceType;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Entity(repositoryClass=ActivityRepository::class)
 * @InheritanceType("JOINED")
 */
class Activity implements PictorialObject
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     * @Assert\Type(type="numeric", message=" id is not valid")
     */
    protected  $id;

    /**
     * @ORM\Column(type="boolean")
     * @Assert\Type(type="bool", message=" isPublic not valid boolean")
     */
    protected  $isPublic = false;

    /**
     * @ORM\Column(type="string", length=50)
     * @Assert\NotBlank(message="the title is required")
     * @Assert\Type(type="string", message=" title is not valid string")
     * @Assert\Length(min="2", max="50",
     *     minMessage="the title must be at least 2 characters long",
     *     maxMessage="the title must not exceed 50 characters")
     */
    protected $title;

    //todo add timezone
    /**
     * @ORM\Column(type="date")
     * @Assert\NotBlank(message="the postDate is required")
     * @Assert\Type(type={"DateTime", "Y-m-d"}, message= "the date must be in the format YYYY-mm-dd")
     * @Assert\GreaterThanOrEqual("today", message="post date must be today or greater date")
     */
    protected $postDate;

    /**
     * @ORM\Column(type="json")
     */
    protected $summary = [];

    /**
     * @ORM\ManyToOne(targetEntity=User::class, inversedBy="activities")
     * @ORM\JoinColumn(nullable=false)
     * @Assert\NotBlank(message="the creator is required")
     * @Assert\Type(type={"App\Entity\User", "integer"})
     */
    protected ?User $creator = null;

    /**
     * @ORM\ManyToOne(targetEntity=Project::class, inversedBy="activities")
     * @ORM\JoinColumn(onDelete="SET NULL")
     * @Assert\Type(type={"App\Entity\Project", "integer"})
     */
    protected ?Project $project = null;

    /**
     * @ORM\ManyToOne(targetEntity=Organization::class, inversedBy="activities")
     * @ORM\JoinColumn(onDelete="SET NULL")
     * @Assert\Type(type={"App\Entity\Organization", "integer"})
     */
    protected ?Organization $organization = null;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    protected $picturePath;

    /**
     * base64_encode(picture)
     */
    protected $pictureFile;

    /**
     * @ORM\ManyToMany(targetEntity=User::class, inversedBy="followingActivities")
     * @Assert\Collection(
     *     fields={
     *         @Assert\Type(type="App\Entity\User")
     *     }
     * )
     */
    private $followers;

    private bool $isFollowed = false;

    public function __construct()
    {
        $this->followers = new ArrayCollection();
    }

    public function serialize(String $context = null): array
    {
        $data = [
            "id" => $this->id,
            "title" => $this->title,
            "summary" => $this->summary,
            "postDate" => $this->postDate->format('Y-m-d'),
            "isPublic" => $this->isPublic,
            "creator" => $this->creator->serialize("read_activity"),
            "isFollowed" => $this->isFollowed
        ];

        //Check some attributes to see if they are sets
        if($this->pictureFile){
            $data["picture"] = $this->pictureFile;
        }

        if($this->project && $context === "read_activity"){
            $data["project"] = $this->project->serialize();
        }

        if($this->organization && $context ==="read_activity"){
            $data["organization"] = $this->organization->serialize();
        }

        if(!$this->followers->isEmpty() && $context === "read_activity"){
            //$data["followers"] = $this->followers->toArray();
            $data["followers"] = [];
            foreach($this->followers as $follower){
                array_push($data["followers"], $follower->serialize());
            }
        }

        return $data;
    }

    public function setFromActivityFile(Activity $activityFile){
        $this->isPublic = $activityFile->getIsPublic();
        $this->title = $activityFile->getTitle();
        $this->summary = $activityFile->getSummary();
        $this->postDate = $activityFile->getPostDate();
        $this->picturePath = $activityFile->getPicturePath();
        $this->creator = $activityFile->getCreator();
        $this->project = $activityFile->getProject();
        $this->organization = $activityFile->getOrganization();
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

    public function getPostDate(): ?\DateTimeInterface
    {
        return $this->postDate;
    }

    public function setPostDate(\DateTimeInterface $postDate): self
    {
        $this->postDate = $postDate;

        return $this;
    }

    /**
     * @return array
     */
    public function getSummary(): array
    {
        return $this->summary;
    }

    /**
     * @param array $summary
     */
    public function setSummary(array $summary): void
    {
        $this->summary = $summary;
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
    public function getPictureFile() : ?String
    {
        return $this->pictureFile;
    }

    /**
     * @param mixed $pictureFile
     */
    public function setPictureFile($pictureFile): self
    {
        $this->pictureFile = $pictureFile;

        return $this;
    }

    /**
     * @return Collection|User[]
     */
    public function getFollowers(): Collection
    {
        return $this->followers;
    }

    public function addFollower(UserInterface $follower): self
    {
        if (!$this->followers->contains($follower)) {
            $this->followers[] = $follower;
        }

        return $this;
    }

    public function removeFollower(UserInterface $follower): self
    {
        $this->followers->removeElement($follower);

        return $this;
    }

    public function isFollowByUserId(int $userId){
        $res = false;
        foreach($this->followers as $follower){
            if($follower->getId() === $userId){
                $res = true;
            }
        }
        return $res;
    }

    /**
     * @return bool
     */
    public function isFollowed(): bool
    {
        return $this->isFollowed;
    }

    /**
     * @param bool $isFollowed
     */
    public function setIsFollowed(bool $isFollowed): void
    {
        $this->isFollowed = $isFollowed;
    }


    /*
     * return if User|Project|Organization have a private to this.Activity.
     * @param $user
     * @return bool
     */
    /*public function hasAccess($user): bool
    {
        $res = false;
        if($this->creator->getId() === $user->getId()){
            $res = true;
        }
        else if($this->project !== null && $this->project->isAssign($user)){
            $res = true;
        }
        else if($this->organization !== null && $this->organization->isMember($user)){
            $res = true;
        }

        return $res;
    }*/
}
