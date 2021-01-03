<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use App\Repository\UserRepository;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

/**
 * @ORM\Entity(repositoryClass=UserRepository::class)
 * @UniqueEntity("email")
 *
 */
class User implements UserInterface
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private ?int $id;

    /**
     * @ORM\Column(type="json")
     */
    private array $roles = [];

    /**
     * @ORM\Column(type="string", length=50)
     * @Assert\NotBlank(message="the firstname is required")
     * @Assert\Length(min="2", max="50",
     *     minMessage="the firstname must be at least 2 characters long",
     *     maxMessage="the firstname must not exceed 50 characters")
     */
    private ?string $firstname;

    /**
     * @ORM\Column(type="string", length=50)
     * @Assert\NotBlank(message="the lastname is required")
     * @Assert\Length(min="2", max="50",
     *     minMessage="the lastname must be at least 2 characters long",
     *     maxMessage="the lastname must not exceed 50 characters")
     */
    private ?string $lastname;

    /**
     * @ORM\Column(type="string", length=50, unique=true)
     * @Assert\NotBlank(message="the email is required")
     * @Assert\Email(message="invalid email")
     */
    private ?string $email;

    /**
     * @ORM\Column(type="string", length=13, nullable=true)
     */
    private ?string $phone = null;

    /**
     * @ORM\Column(type="string", length=13, nullable=true)
     */
    private ?string $mobile = null;

    /**
     * @ORM\Column(type="string", length=255)
     * @Assert\Length(min="6", max="20",
     *     minMessage="the password must be between 6 and 20 characters",
     *     maxMessage="the password must be between 6 and 20 characters)
     * @Assert\Regex(pattern="/^(?=.*[a-z])(?=.*\d).{6,}$/i",
     *     message="the password must be at least 6 characters long and include at least one letter and one number")
     */
    private ?string $password;

    /**
     * @ORM\OneToMany(targetEntity=GlobalPropertyAttribute::class, mappedBy="user")
     */
    private $globalPropertyAttributes;

    public function __construct()
    {
        $this->globalPropertyAttributes = new ArrayCollection();
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
    public function getGPA(): Collection
    {
        return $this->globalPropertyAttributes;
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



}
