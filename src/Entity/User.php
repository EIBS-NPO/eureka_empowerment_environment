<?php

namespace App\Entity;

use App\Entity\Interfaces\AddressableObject;
use App\Entity\Interfaces\PictorialObject;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use App\Repository\UserRepository;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Entity(repositoryClass=UserRepository::class)
 * @UniqueEntity("email", message="this email already exist for user account")
 *
 */
class User implements UserInterface, PictorialObject, AddressableObject
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     * @Assert\Type(type="numeric", message=" id is not valid")
     */
    private ?int $id = null;

    /**
     * @ORM\Column(type="json")
     */
    private array $roles = [];

    /**
     * @ORM\Column(type="string", length=50)
     * @Assert\NotBlank(message="the firstname is required")
     * @Assert\Type(type="string", message=" firstname is not valid string")
     * @Assert\Length(min="2", max="50",
     *     minMessage="the firstname must be at least 2 characters long",
     *     maxMessage="the firstname must not exceed 50 characters")
     */
    private ?string $firstname;

    /**
     * @ORM\Column(type="string", length=50)
     * @Assert\NotBlank(message="the lastname is required")
     * @Assert\Type(type="string", message=" lastname is not valid string")
     * @Assert\Length(min="2", max="50",
     *     minMessage="the lastname must be at least 2 characters long",
     *     maxMessage="the lastname must not exceed 50 characters")
     */
    private ?string $lastname;

    /**
     * @ORM\Column(type="string", length=50, unique=true)
     * @Assert\NotBlank(message="the email is required")
     * @Assert\Type(type="string", message=" email is not valid string")
     * @Assert\Email(message = "The email '{{ value }}' is not a valid email.")
     */
    private ?string $email;

    //todo regex for french/belgian phone number
    /**
     * @ORM\Column(type="string", length=13, nullable=true)
     * @Assert\Type(type="string", message=" phone is not valid string")
     */
    private ?string $phone = null;

    //todo regex for french/belgian phone number
    /**
     * @ORM\Column(type="string", length=13, nullable=true)
     * @Assert\Type(type="string", message=" mobile is not valid string")
     */
    private ?string $mobile = null;

    /**
     * @ORM\Column(type="string", length=255)
     * @Assert\Type(type="string", message=" password is not valid string")
     * @Assert\Length(min="6", max="20",
     *     minMessage="the password must be between 6 and 20 characters",
     *     maxMessage="the password must be between 6 and 20 characters"
     * )
     * @Assert\Regex(pattern="/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{6,20}$/",
     *     message="Length between 6 and 20 with 1 upper case, 1 lower case and 1 digit."
     * )
     */
    private ?string $password;

    /**
     * @ORM\OneToMany(targetEntity=GlobalPropertyAttribute::class, mappedBy="user")
     * @Assert\Collection(
     *     fields={
     *         @Assert\Type(type="App\Entity\GlobalPropertyAttribute")
     *     }
     * )
     */
    private $globalPropertyAttributes;

    /**
     * @ORM\OneToMany(targetEntity=Organization::class, mappedBy="referent")
     * @Assert\Collection(
     *     fields={
     *         @Assert\Type(type="App\Entity\Organization")
     *     }
     * )
     */
    private $organizations;

    /**
     * @ORM\OneToMany(targetEntity=Project::class, mappedBy="creator")
     * @Assert\Collection(
     *     fields={
     *         @Assert\Type(type="App\Entity\Project")
     *     }
     * )
     */
    private $projects;

    /**
     * @ORM\ManyToMany(targetEntity=Organization::class, inversedBy="membership")
     * @Assert\Collection(
     *     fields={
     *         @Assert\Type(type="App\Entity\Organization")
     *     }
     * )
     */
    private $memberOf;

    /**
     * @ORM\OneToMany(targetEntity=Activity::class, mappedBy="creator")
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
    private $picturePath = null;

    /**
     * base64_encode(picture)
     */
    private $pictureFile = null;

    /**
     * @ORM\OneToOne(targetEntity=Address::class, inversedBy="userOwner", cascade={"persist", "remove"})
     */
    private $address;

    /**
     * @ORM\ManyToMany(targetEntity=Activity::class, mappedBy="followers")
     * @Assert\Collection(
     *     fields={
     *         @Assert\Type(type="App\Entity\Activity")
     *     }
     * )
     */
    private $followingActivities;

    /**
     * @ORM\OneToMany(targetEntity=FollowingProject::class, mappedBy="follower")
     * @Assert\Collection(
     *     fields={
     *         @Assert\Type(type="App\Entity\FollowingProject")
     *     }
     * )
     */
    private $followingProjects;


    private bool $isConfirmed = true;
    /*
     * @ORM\Column(type="string", length=255, nullable=true)
     */
   // private ?String $activationToken = null;

    /*
     * @ORM\OneToOne(targetEntity=JwtRefreshToken::class, mappedBy="user")
     */
 //   private ?JwtRefreshToken $refreshToken = null;

    public function __construct()
    {
        $this->globalPropertyAttributes = new ArrayCollection();
        $this->organizations = new ArrayCollection();
        $this->projects = new ArrayCollection();
        $this->memberOf = new ArrayCollection();
        $this->activities = new ArrayCollection();
        $this->followingActivities = new ArrayCollection();
        $this->followingProjects = new ArrayCollection();
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
            "firstname" => $this->firstname,
            "lastname" => $this->lastname,
            "email" => $this->email,
            "roles" => $this->roles[0],
            "isConfirmed" => $this->isConfirmed()
        ];

        //Check some attributes to see if they are sets
        if($this->phone){
            $data["phone"] = $this->phone;
        }

        if($this->mobile){
            $data["mobile"] = $this->mobile;
        }

        if($this->pictureFile){
            $data["picture"] = $this->pictureFile;
        }

        if($this->address){
            $data["address"] = $this->address->serialize();
        }

        if(!$this->getGPA()->isEmpty()){
            $data["gpAttributes"] = [];
            foreach($this->getGpa() as $gpa){
                $data["gpAttributes"][$gpa->getPropertyKey()] = $gpa->serialize();
            }
        }

        //todo envoyer isConfirmed et isActived a plaer dans les attributs by getters

       /* if(!$this->followingActivities->isEmpty() && $context !== "read_activity"){
            //$data["followingActivities"] = $this->followingActivities->toArray();
            $data["followingActivities"] = [];
            foreach($this->followingActivities as $activity){
                array_push($data["followingActivities"], $activity->getId());
            }
        }*/


        /*if($this->organizations && $context != "read_organization" && $context != "read_project"){
            $data["organization"] = [];
            foreach($this->organizations as $org){
                array_push($data["organization"], $org->serialize("read_referent"));
            }
        }*/

        return $data;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRoles(): ?array
    {
        return $this->roles;
    }

    public function setRoles(array $roles): self
    {
        $this->roles = $roles;

        return $this;
    }

    public function getFirstname(): ?string
    {
        return $this->firstname;
    }

    public function setFirstname(string $firstname): self
    {
        $this->firstname = $firstname;

        return $this;
    }

    public function getLastname(): ?string
    {
        return $this->lastname;
    }

    public function setLastname(string $lastname): self
    {
        $this->lastname = $lastname;

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

    public function getMobile(): ?string
    {
        return $this->mobile;
    }

    public function setMobile(?string $mobile): self
    {
        $this->mobile = $mobile;

        return $this;
    }

    public function getPassword()
    {
        return $this->password;
        // TODO: Implement getPassword() method.
    }

    public function getSalt()
    {
        // TODO: Implement getSalt() method.
    }

    public function getUsername()
    {
        return $this->email;
    }

    public function eraseCredentials()
    {
        // TODO: Implement eraseCredentials() method.
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;

        return $this;
    }

    /**
     * @return Collection|GlobalPropertyAttribute[]
     */
    public function getGPA(string $key = null): Collection
    {
       if(is_null($key)) {
           return $this->globalPropertyAttributes;
       }else{
           $rsl = new ArrayCollection();
           foreach($this->globalPropertyAttributes as $gpa){
               if($gpa->getPropertyKey() === $key) $rsl->add($gpa);
           }
           return $rsl;
       }
    }

    /**
     * @param ArrayCollection $globalPropertyAttributes
     */
    public function setGlobalPropertyAttributes(ArrayCollection $globalPropertyAttributes): void
    {
        $this->globalPropertyAttributes = $globalPropertyAttributes;
    }



    public function addGlobalPropertyAttribute(GlobalPropertyAttribute $globalPropertyAttribute): self
    {
        if (!$this->globalPropertyAttributes->contains($globalPropertyAttribute)) {
            $this->globalPropertyAttributes[] = $globalPropertyAttribute;
            $globalPropertyAttribute->setUser($this);
        }

        return $this;
    }

    public function removeGlobalPropertyAttribute(GlobalPropertyAttribute $globalPropertyAttribute): self
    {
        if ($this->globalPropertyAttributes->removeElement($globalPropertyAttribute)) {
            // set the owning side to null (unless already changed)
            if ($globalPropertyAttribute->getUser() === $this) {
                $globalPropertyAttribute->setUser(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|Organization[]
     */
    public function getOrganizations(): Collection
    {
        return $this->organizations;
    }

    public function addOrganization(Organization $organization): self
    {
        if (!$this->organizations->contains($organization)) {
            $this->organizations[] = $organization;
            $organization->setReferent($this);
        }

        return $this;
    }

    public function getOrgById($orgId){
        $res = false;
        foreach($this->organizations as $org){
            if($org->getId() === (int)$orgId){
                $res = $org;
            }
        }
        return $res;
    }

    public function getMemberOfById($orgId){
        $res = false;
        foreach($this->memberOf as $org){
            if($org->getId() === $orgId){
                $res = $org;
            }
        }
        return $res;
    }

    public function removeOrganization(Organization $organization): self
    {
        if ($this->organizations->removeElement($organization)) {
            // set the owning side to null (unless already changed)
            if ($organization->getReferent() === $this) {
                $organization->setReferent(null);
            }
        }

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
            $project->setCreator($this);
        }

        return $this;
    }

    public function getProjectById($projectId){
        $res = null;
        foreach($this->projects as $project){
            if($project->getId() === (int)$projectId){
                $res = $project;
            }
        }
        return $res;
    }

    public function removeProject(Project $project): self
    {
        if ($this->projects->removeElement($project)) {
            // set the owning side to null (unless already changed)
            if ($project->getCreator() === $this) {
                $project->setCreator(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|Organization[]
     */
    public function getMemberOf(): Collection
    {
        return $this->memberOf;
    }

    public function addMembership(Organization $membership): self
    {
        if (!$this->memberOf->contains($membership)) {
            $this->memberOf[] = $membership;
        }

        return $this;
    }

    public function removeMembership(Organization $membership): self
    {
        $this->memberOf->removeElement($membership);

        return $this;
    }

    /**
     * @return Collection|Activity[]
     */
    public function getActivities(): Collection
    {
        return $this->activities;
    }

    public function getActivity($activityId){
        $res = null;
        //look into created project
        foreach($this->activities as $activity) {
            if ($activity->getId() === (int)$activityId) {
                $res = $activity;
            }
        }
        return $res;
    }

    public function addActivity(Activity $activity): self
    {
        if (!$this->activities->contains($activity)) {
            $this->activities[] = $activity;
            $activity->setCreator($this);
        }

        return $this;
    }

    public function removeActivity(Activity $activity): self
    {
        if ($this->activities->removeElement($activity)) {
            // set the owning side to null (unless already changed)
            if ($activity->getCreator() === $this) {
                $activity->setCreator(null);
            }
        }

        return $this;
    }

    /**
     * @return string|null
     */
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
    public function getPictureFile() :String
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

    public function getAddress(): ?Address
    {
        return $this->address;
    }

    public function setAddress(?Address $address): self
    {
        $this->address = $address;

        return $this;
    }

    /**
     * @return Collection|Activity[]
     */
    public function getFollowingActivities(): Collection
    {
        return $this->followingActivities;
    }

    public function addFollowingActivity(Activity $followingActivity): self
    {
        if (!$this->followingActivities->contains($followingActivity)) {
            $this->followingActivities[] = $followingActivity;
            $followingActivity->addFollower($this);
        }

        return $this;
    }

    public function removeFollowingActivity(Activity $followingActivity): self
    {
        if ($this->followingActivities->removeElement($followingActivity)) {
            $followingActivity->removeFollower($this);
        }

        return $this;
    }

    /**
     * @return Collection|FollowingProject[]
     */
    public function getFollowingProjects(): Collection
    {
        return $this->followingProjects;
    }

    public function addFollowingProject(FollowingProject $followingProject): self
    {
        if (!$this->followingProjects->contains($followingProject)) {
            $this->followingProjects[] = $followingProject;
            $followingProject->setFollower($this);
        }

        return $this;
    }

    public function removeFollowingProject(FollowingProject $followingProject): self
    {
        if ($this->followingProjects->removeElement($followingProject)) {
            // set the owning side to null (unless already changed)
            if ($followingProject->getFollower() === $this) {
                $followingProject->setFollower(null);
            }
        }

        return $this;
    }

    public function getAssignedProjects(){
        $res = [];
        foreach($this->followingProjects as $following){
            if($following->getIsAssigning() === true ){
                $res[] = $following->getObject();
            }
        }
        return $res;
    }

    public function getFollowedProjects(): ArrayCollection
    {
        $res = new ArrayCollection();
        foreach($this->followingProjects as $following){
            if($following->getIsFollowing() === true ){
                $res[] = $following->getObject();
            }
        }
        return $res;
    }

    public function getAssignedProjectById($projectId){
        $res = null;
        //look into created project
        foreach($this->projects as $project){
            if($project->getId() === $projectId ){
                $res = $project;
            }
        }// look into assigned project
        if($res === null) {
            foreach($this->getAssignedProjects() as $project){
                if($project->getId() === $projectId ){
                    $res = $project;
                }
            }
        }
        return $res;
    }

    /**
     * @return bool
     */
    public function isConfirmed(): bool
    {
        return $this->isConfirmed;
    }

    /**
     * @param bool $isConfirmed
     */
    public function setIsConfirmed(bool $isConfirmed): void
    {
        $this->isConfirmed = $isConfirmed;
    }
}
