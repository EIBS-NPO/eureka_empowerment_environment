<?php

namespace App\Entity;

use App\Repository\FollowingProjectRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Entity(repositoryClass=FollowingProjectRepository::class)
 */
class FollowingProject
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity=project::class, inversedBy="followers")
     * @ORM\JoinColumn(nullable=false)
     * @Assert\Type(type={"App\Entity\Project", "integer"})
     */
    private $project;

    /**
     * @ORM\ManyToOne(targetEntity=User::class, inversedBy="followingProjects")
     * @ORM\JoinColumn(nullable=false)
     * @Assert\Type(type={"App\Entity\User", "integer"})
     */
    private $follower;

    /**
     * @ORM\Column(type="boolean")
     */
    private $isFollowing;

    /**
     * @ORM\Column(type="boolean")
     */
    private $isAssigning;

    public function serialize(){
        $data =[];

    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProject(): ?project
    {
        return $this->project;
    }

    public function setProject(?project $project): self
    {
        $this->project = $project;

        return $this;
    }

    public function getFollower(): ?User
    {
        return $this->follower;
    }

    public function setFollower(?UserInterface $follower): self
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

    public function isStillValid(){
        return $this->isAssigning || $this->isFollowing;
    }
}
