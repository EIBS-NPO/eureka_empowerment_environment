<?php

namespace App\Entity;

use App\Repository\EventsRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Entity(repositoryClass=EventsRepository::class)
 */
class Events
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     * @Assert\Type(type="numeric", message=" id is not valid")
     */
    private ?int $id;

    /**
     * @ORM\ManyToOne(targetEntity=User::class)
     * @ORM\JoinColumn(nullable=false)
     */
    private ?UserInterface $actor;

    /**
     * @ORM\Column(type="integer")
     */
    private ?int $target;

    /**
     * @ORM\Column(type="string", length=20)
     * //todo regex User or Organization or Project
     */
    private ?string $targetType;

    /**
     * @ORM\Column(type="string", length=255)
     * @Assert\Length(max="255", maxMessage="the password must be between 6 and 20 characters")
     */
    private ?string $description;

    /**
     * @ORM\Column(type="datetime")
     */
    private ?\DateTimeInterface $date;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getActor(): ?UserInterface
    {
        return $this->actor;
    }

    public function setActor(UserInterface $actor): self
    {
        $this->actor = $actor;

        return $this;
    }

    public function getTarget(): ?int
    {
        return $this->target;
    }

    public function setTarget(int $target): self
    {
        $this->target = $target;

        return $this;
    }

    public function getTargetType(): ?string
    {
        return $this->targetType;
    }

    public function setTargetType(string $targetType): self
    {
        $this->targetType = $targetType;

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

    public function getDate(): ?\DateTimeInterface
    {
        return $this->date;
    }

    public function setDate(\DateTimeInterface $date): self
    {
        $this->date = $date;

        return $this;
    }
}
