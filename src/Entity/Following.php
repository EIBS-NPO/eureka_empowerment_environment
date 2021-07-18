<?php

namespace App\Entity;

use App\Entity\Interfaces\TrackableObject;
use App\Repository\FollowingProjectRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Entity(repositoryClass=FollowingProjectRepository::class)
 */
class Following
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    //todo rendre dynamique (heritage? polymorphe?)
    /**
     * @ORM\ManyToOne(targetEntity=project::class, inversedBy="followers")
     * @ORM\JoinColumn(nullable=false)
     * @Assert\Type(type={"App\Entity\Project", "integer"})
     */
    private TrackableObject $object;

    /**
     * @ORM\ManyToOne(targetEntity=User::class, inversedBy="followingProjects")
     * @ORM\JoinColumn(nullable=false)
     * @Assert\Type(type={"App\Entity\User", "integer"})
     */
    private UserInterface $follower;

    /**
     * @ORM\Column(type="boolean")
     */
    private bool $isFollowing;

    /**
     * @ORM\Column(type="boolean")
     */
    private bool $isAssigning;

    public function serialize(){
        $data["projectId"] = $this->object->getId();
        $data["followerId"] = $this->follower->getId();
        $data["isFollowing"] = $this->isFollowing;
        $data["isAssigning"] = $this->isAssigning;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getObject(): TrackableObject
    {
        return $this->object;
    }

    public function setObject(TrackableObject $object): self
    {
        $this->object = $object;

        return $this;
    }

    public function getFollower(): UserInterface
    {
        return $this->follower;
    }

    public function setFollower( UserInterface $follower): self
    {
        $this->follower = $follower;

        return $this;
    }

    public function getIsFollowing(): ?bool
    {
        return $this->isFollowing;
    }

    public function setIsFollowing(bool $isFollowing): self
    {
        $this->isFollowing = $isFollowing;

        return $this;
    }

    public function getIsAssigning(): ?bool
    {
        return $this->isAssigning;
    }

    public function setIsAssigning(bool $isAssigning): self
    {
        $this->isAssigning = $isAssigning;

        return $this;
    }
}
