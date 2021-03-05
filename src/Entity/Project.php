<?php

namespace App\Entity;

use App\Repository\ProjectRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Entity(repositoryClass=ProjectRepository::class)
 */
class Project
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     * @Assert\Type(type="numeric", message=" id is not valid")
     */
    private ?int $id;

    /**
     * @ORM\Column(type="string", length=50)
     * @Assert\NotBlank(message="the title is required")
     * @Assert\Length(min="2", max="50",
     *     minMessage="the title must be at least 2 characters long",
     *     maxMessage="the title must not exceed 50 characters")
     */
    private ?string $title;

    //todo add timezone
    /**
     * @ORM\Column(type="date")
     * @Assert\NotBlank(message="the startDate is required")
     * @Assert\Type(type={"DateTime", "Y-m-d"}, message= "the date must be in the format Y-m-d")
     * @Assert\LessThanOrEqual(propertyPath="endDate", message="start date must be less or equal than end date")
     */
    private ?\DateTimeInterface $startDate = null;

    /**
     * @ORM\Column(type="date", nullable=true)
     * @Assert\Type(type={"DateTime", "Y-m-d"}, message= "the date must be a DateTime Object")
     * @Assert\GreaterThanOrEqual(propertyPath="startDate", message="end date must be greater or equal than start date")
     */
    private ?\DateTimeInterface $endDate = null;

    /**
     * @ORM\ManyToOne(targetEntity=User::class, inversedBy="projects")
     * @ORM\JoinColumn(nullable=false)
     * @Assert\Type(type={"App\Entity\User", "integer"})
     */
    private ?User $creator;

    /**
     * @ORM\ManyToOne(targetEntity=Organization::class, inversedBy="projects")
     * @Assert\Type(type={"App\Entity\Organization", "integer"})
     */
    private ?Organization $organization = null;

    /**
     * @ORM\OneToMany(targetEntity=Activity::class, mappedBy="project")
     * @Assert\Collection(
     *     fields={
     *         @Assert\Type(type="App\Entity\Activity")
     *     }
     * )
     */
    private $activities;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $picturePath;

    /**
     * base64_encode(picture)
     */
    private $pictureFile;

    /**
     * @ORM\Column(type="json")
     */
    private $description = [];

    /**
     * @ORM\OneToMany(targetEntity=FollowingProject::class, mappedBy="project")
     * @Assert\Collection(
     *     fields={
     *         @Assert\Type(type="App\Entity\FollowingProject")
     *     }
     * )
     */
    private $followings;

    public function __construct()
    {
        $this->followings = new ArrayCollection();
    }

    /**
     * Return an array containing object attributes
     * $context allows to give a context to avoid circular references
     * @param String|null $context
     * @return array
     */
    public function serialize(String $context = null): array
    {
        $data = [
            "id" => $this->id,
            "title" => $this->title,
            "description" => $this->description,
            "startDate" => $this->startDate->format('Y-m-d'),
            "creator" => $this->creator->serialize()
        ];

        //Check some attributes to see if they are sets
        if($this->endDate){
            $data["endDate"] = $this->endDate->format('Y-m-d');
        }

        if($this->pictureFile){
            $data["picture"] = $this->pictureFile;
        }

        /*if($context != "creator"){
            $data["creator"] = $this->creator->serialize("read_project");
        }*/

        //Check some attributes with contexts to see if they are sets
        if($this->organization && $context === "read_project"){
            $data["organization"] = $this->organization->serialize();
        }

        if($this->activities !== null && $context === "read_project"){
            $data["activities"] = [];
            foreach($this->activities as $activity){
                array_push($data["activities"], $activity->serialize());
            }
        }

        return $data;
    }

    public function getId(): ?int
    {
        return $this->id;
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

    /**
     * @return array
     */
    public function getDescription(): array
    {
        return $this->description;
    }

    /**
     * @param array $description
     */
    public function setDescription(array $description): void
    {
        $this->description = $description;
    }

    public function getStartDate(): ?\DateTimeInterface
    {
        return $this->startDate;
    }

    public function setStartDate(\DateTimeInterface $startDate): self
    {
        $this->startDate = $startDate;

        return $this;
    }

    public function getEndDate(): ?\DateTimeInterface
    {
        return $this->endDate;
    }

    public function setEndDate(?\DateTimeInterface $endDate): self
    {
        $this->endDate = $endDate;

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

    public function getOrganization(): ?Organization
    {
        return $this->organization;
    }

    public function setOrganization(?Organization $organization): self
    {
        $this->organization = $organization;

        return $this;
    }

    public function getActivities()
    {
        return $this->activities;
    }

    /**
     * @param int $activityId
     * @return Activity|null
     */
    public function getActivityById(int $activityId){
        $res = null;
        foreach($this->activities as $activity ){
            if($activity->getId() === $activityId){
                $res = $activity;
            }
        }
        return $res;
    }

    public function setActivities( $activities): self
    {
        // unset the owning side of the relation if necessary
        if ($activities === null && $this->activities !== null) {
            $this->activities->setProject(null);
        }

        // set the owning side of the relation if necessary
        if ($activities !== null && $activities->getProject() !== $this) {
            $activities->setProject($this);
        }

        $this->activities = $activities;

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

    /**
     * @return Collection|FollowingProject[]
     */
    public function getFollowings(): Collection
    {
        return $this->followings;
    }

    public function addFollowing(FollowingProject $following): self
    {
        if (!$this->followings->contains($following)) {
            $this->followings[] = $following;
            $following->setProject($this);
        }

        return $this;
    }

    public function removeFollowing(FollowingProject $following): self
    {
        if ($this->followings->removeElement($following)) {
            // set the owning side to null (unless already changed)
            if ($following->getProject() === $this) {
                $following->setProject(null);
            }
        }

        return $this;
    }

    public function getAssignedTeam(){
        $team = [];
        foreach($this->followings as $following){
            if($following->getIsAssigning()){
                $team[]=$following->getFollower();
            }
        }
        return $team;
    }

    public function getFollowers(){
        $followers = [];
        foreach($this->followings as $following){
            if($following->getIsFollowing()){
                $followers[]=$following;
            }
        }
        return $followers;
    }

    /**
     *
     * @param int $userId
     * @return FollowingProject|null
     */
    public function getFollowingByUserId(int $userId){
        $res = null;
        foreach($this->followings as $following){
            if($following->getFollower()->getId() === $userId){
                $res = $following;
            }
        }
        return $res;
    }
}
