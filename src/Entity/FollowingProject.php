<?php

namespace App\Entity;

use App\Repository\FollowingProjectRepository;
use Doctrine\ORM\Mapping as ORM;
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
     * @ORM\Column(type="boolean")
     */
    private $isAssigned;

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

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getIsAssigned(): ?bool
    {
        return $this->isAssigned;
    }

    public function setIsAssigned(bool $isAssigned): self
    {
        $this->isAssigned = $isAssigned;

        return $this;
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

    public function setFollower(?User $follower): self
    {
        $this->follower = $follower;

        return $this;
    }
}
