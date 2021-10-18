<?php

namespace App\Entity;

use App\Entity\Interfaces\AddressableObject;
use App\Repository\AddressRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Entity(repositoryClass=AddressRepository::class)
 */
class Address
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     * @Assert\Type(type="numeric", message=" id is not valid")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255)
     * @Assert\NotBlank(message="address is required")
     * @Assert\Length(min="2", max="255",
     *     minMessage="the address must be at least 2 characters long",
     *     maxMessage="the address must not exceed 255 characters")
     */
    private $address;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     * @Assert\Length(max="255",
     *     maxMessage="the complement must not exceed 255 characters")
     */
    private $complement;

    /**
     * @ORM\Column(type="string", length=30)
     * @Assert\NotBlank(message="country is required")
     * @Assert\Length(min="2", max="30",
     *     minMessage="the country must be at least 2 characters long",
     *     maxMessage="the country must not exceed 30 characters")
     */
    private $country;

    /**
     * @ORM\Column(type="string", length=10)
     * @Assert\NotBlank(message="zipCode is required")
     * @Assert\Length(min="2", max="10",
     *     minMessage="the zipCode must be at least 2 characters long",
     *     maxMessage="the zipCode must not exceed 10 characters")
     */
    private $zipCode;

    //todo check regex
    /**
     * @ORM\Column(type="float", nullable=true)
     * @Assert\Regex(
     *     pattern="/^(\-?\d+(\.\d+)?),\s*(\-?\d+(\.\d+)?)$/",
     *     match=false,
     *     message="bad latitude format"
     * )
     */
    private $latitude;

    /**
     * @ORM\Column(type="float", nullable=true)
     * @Assert\Regex(
     *     pattern="/^(\-?\d+(\.\d+)?),\s*(\-?\d+(\.\d+)?)$/",
     *     match=false,
     *     message="bad longitude format"
     * )
     */
    private ?float $longitude;

    /**
     * @ORM\Column(type="string", length=15)
     * @Assert\Regex(
     *     pattern="/User|Organization/",
     *     message="bad ownerType"
     * )
     */
    private ?string $ownerType;

    /**
     * @ORM\OneToOne(targetEntity=User::class, mappedBy="address", cascade={"persist", "remove"})
     * @Assert\Type(type={"App\Entity\User", "integer"})
     */
    private ?User $userOwner = null;

    /**
     * @ORM\OneToOne(targetEntity=Organization::class, mappedBy="address", cascade={"persist", "remove"})
     * @Assert\Type(type={"App\Entity\Organization", "integer"})
     */
    private ?Organization $orgOwner = null;

    /**
     * @ORM\Column(type="string", length=50)
     * @Assert\NotBlank(message="city is required")
     * @Assert\Length(min="2", max="50",
     *     minMessage="the city must be at least 2 characters long",
     *     maxMessage="the city must not exceed 50 characters")
     */
    private $city;

    public function serialize(String $context = null): array
    {
        $data = [
            "id" => $this->id,
            "address" => $this->address,
            "country" => $this->country,
            "city" => $this->city,
            "zipCode" => $this->zipCode,
        ];

        if($this->address !== null){
            $data["complement"] = $this->complement;
        }

        //Check some attributes to see if they are sets
        if($this->userOwner !== null && $context === "read_address"){
            $data["owner"] = $this->userOwner->serialize();
        }

        if($this->orgOwner !== null && $context === "read_address"){
            $data["orgOwner"] = $this->orgOwner->serialize();
        }

        return $data;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(string $address): self
    {
        $this->address = $address;

        return $this;
    }

    public function getComplement(): ?string
    {
        return $this->complement;
    }

    public function setComplement(?string $complement): self
    {
        $this->complement = $complement;

        return $this;
    }

    public function getCountry(): ?string
    {
        return $this->country;
    }

    public function setCountry(string $country): self
    {
        $this->country = $country;

        return $this;
    }

    public function getZipCode(): ?string
    {
        return $this->zipCode;
    }

    public function setZipCode(string $zipCode): self
    {
        $this->zipCode = $zipCode;

        return $this;
    }

    public function getLatitude(): ?float
    {
        return $this->latitude;
    }

    public function setLatitude(?float $latitude): self
    {
        $this->latitude = $latitude;

        return $this;
    }

    public function getLongitude(): ?float
    {
        return $this->longitude;
    }

    public function setLongitude(?float $longitude): self
    {
        $this->longitude = $longitude;

        return $this;
    }

    public function getOwnerType(): ?string
    {
        return $this->ownerType;
    }

    public function setOwnerType(string $ownerType): self
    {
        $this->ownerType = $ownerType;

        return $this;
    }

    /**
     * @return User|null
     */
    public function getUserOwner(): ?User
    {
        return $this->userOwner;
    }

    /**
     * @param User|null $userOwner
     */
    public function setUserOwner(?User $userOwner): void
    {
        $this->userOwner = $userOwner;
    }


   /* public function getUserOwner(): ?User
    {
        return $this->userOwner;
    }*/

   /* public function setUserOwner(?User $owner): self
    {
        // unset the owning side of the relation if necessary
        if ($owner === null && $this->userOwner !== null) {
            $this->userOwner->setAddress(null);
        }

        // set the owning side of the relation if necessary
        if ($owner !== null && $owner->getAddress() !== $this) {
            $owner->setAddress($this);
        }

        $this->owner = $owner;

        return $this;
    }*/

    public function getOrgOwner(): ?Organization
    {
        return $this->orgOwner;
    }

   /* public function setOwner($object): self
    {
        $classname = get_class($object);
        if($classname === User::class){
            $this->userOwner = $object;
        }else if($classname === Organization::class){
            $this->orgOwner = $object;
        }
        $this->ownerType = $classname;

        return $this;
    }*/

   /* public function setOrgOwner(?Organization $orgOwner): self
    {
        // unset the owning side of the relation if necessary
        if ($orgOwner === null && $this->orgOwner !== null) {
            $this->orgOwner->setAddress(null);
        }

        // set the owning side of the relation if necessary
        if ($orgOwner !== null && $orgOwner->getAddress() !== $this) {
            $orgOwner->setAddress($this);
        }

        $this->orgOwner = $orgOwner;

        return $this;
    }*/

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function setCity(string $city): self
    {
        $this->city = $city;

        return $this;
    }
}
