<?php

namespace App\Entity;

use App\Repository\OrganizationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Entity(repositoryClass=OrganizationRepository::class)
 * @UniqueEntity("name", message="this organization name already exist")
 */
class Organization
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     * @Assert\Type(type="numeric", message="The id is not valid")
     */
    private ?int $id;

    /**
     * @ORM\Column(type="string", length=50)
     * @Assert\NotBlank(message="the type is required")
     * @Assert\Length(min="2", max="50",
     *     minMessage="the type must be at least 2 characters long",
     *     maxMessage="the type must not exceed 50 characters")
     *
     */
    private $type;

    /**
     * @ORM\Column(type="string", length=50, unique=true)
     * @Assert\NotBlank(message="the name is required")
     * @Assert\Length(min="2", max="50",
     *     minMessage="the name must be at least 2 characters long",
     *     maxMessage="the name must not exceed 50 characters")
     */
    private $name;

    /**
     * @ORM\Column(type="string", length=50)
     * @Assert\NotBlank(message="the email is required")
     * @Assert\Email(message="invalid email")
     */
    private $email;

    /**
     * @ORM\Column(type="string", length=13, nullable=true)
     */
    private $phone;

    /**
     * @ORM\ManyToOne(targetEntity=User::class, inversedBy="organizations")
     * @ORM\JoinColumn(nullable=false)
     */
    private ?User $referent;

    /**
     * @ORM\OneToMany(targetEntity=Project::class, mappedBy="organization")
     */
    private $projects = null;

    /**
     * @ORM\ManyToMany(targetEntity=User::class, mappedBy="memberOf")
     */
    private $membership = null;

    /**
     * @ORM\OneToMany(targetEntity=Activity::class, mappedBy="organization")
     */
    private $activities = null;

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
     * @ORM\OneToOne(targetEntity=Address::class, inversedBy="orgOwner", cascade={"persist", "remove"})
     */
    private $address;

    /**
     * @ORM\Column(type="boolean")
     */
    private $isPartner = false;

    public function __construct()
    {
        $this->projects = new ArrayCollection();
        $this->membership = new ArrayCollection();
        $this->activities = new ArrayCollection();
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
            "type" => $this->type,
            "name" => $this->name,
            "email" => $this->email,
            "referent" => $this->referent->serialize()
        ];

        //Check some attributes to see if they are sets
        if($this->phone){
            $data["phone"] = $this->phone;
        }

        if($this->pictureFile){
            $data["picture"] = $this->pictureFile;
        }

        if($this->description){
            $data["description"] = $this->description;
        }

        if($this->address){
            $data["address"] = $this->address->serialize();
        }

        if(!$this->membership->isEmpty()){
            $data["membership"] = $this->membership->toArray();
        }

        if(!$this->activities->isEmpty() && $context === "read_org"){
            $data["activities"] = [];
            foreach($this->activities as $activity){
                array_push($data["activities"], $activity->serialize("read_org"));
            }
        }

        if(!$this->projects->isEmpty() && $context ==="read_org"){
            $data["projects"] = [];
            foreach($this->projects as $project){
                array_push($data["projects"], $project->serialize("read_org"));
            }
        }

        /*if(count($this->membership) > 0 ){
            $data["membership"] = [];
            foreach($this->membership as $member){
                array_push($data["membership"], $member->serialize());
            }
        }*/

        //todo deprecated
        //Check some attributes with contexts to see if they are sets
        /*if($this->referent && $context != "read_referent"){
            $data["referent"] = $this->referent->serialize("read_organization");
        }

        if($this->projects && $context != "read_project" && $context != "read_organization"){
            $data["projects"] = [];
            foreach($this->projects as $project){
                array_push($data["projects"], $project->serialize("read_organization"));
            }
        }*/

        return $data;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;

        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): self
    {
        $this->phone = $phone;

        return $this;
    }

    public function getReferent(): ?User
    {
        return $this->referent;
    }

    public function setReferent(?UserInterface $referent): self
    {
        $this->referent = $referent;

        return $this;
    }

    /**
     * @return Collection|Project[]
     */
    public function getProjects(): Collection
    {
        return $this->projects;
    }

    public function addProject(Project $project): self
    {
        if (!$this->projects->contains($project)) {
            $this->projects[] = $project;
            $project->setOrganization($this);
        }

        return $this;
    }

    public function removeProject(Project $project): self
    {
        if ($this->projects->removeElement($project)) {
            // set the owning side to null (unless already changed)
            if ($project->getOrganization() === $this) {
                $project->setOrganization(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|User[]
     */
    public function getMembership(): Collection
    {
        return $this->membership;
    }

    public function addMembership(User $membership): self
    {
        if (!$this->membership->contains($membership)) {
            $this->membership[] = $membership;
            $membership->addMembership($this);
        }

        return $this;
    }

    public function removeMembership(User $membership): self
    {
        if ($this->membership->removeElement($membership)) {
            $membership->removeMembership($this);
        }

        return $this;
    }

    /**
     * @return Collection|Activity[]
     */
    public function getActivities(): Collection
    {
        return $this->activities;
    }

    public function addActivity(Activity $activity): self
    {
        if (!$this->activities->contains($activity)) {
            $this->activities[] = $activity;
            $activity->setOrganization($this);
        }

        return $this;
    }

    public function removeActivity(Activity $activity): self
    {
        if ($this->activities->removeElement($activity)) {
            // set the owning side to null (unless already changed)
            if ($activity->getOrganization() === $this) {
                $activity->setOrganization(null);
            }
        }

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

    public function getDescription(): ?array
    {
        return $this->description;
    }

    public function setDescription(array $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getAddress(): ?Address
    {
        return $this->address;
    }

    public function setAddress(?Address $address): self
    {
        $this->address = $address;

        return $this;
    }

    public function getIsPartner(): ?bool
    {
        return $this->isPartner;
    }

    public function setIsPartner(bool $isPartner): self
    {
        $this->isPartner = $isPartner;

        return $this;
    }


}
