<?php

namespace App\Entity;

use Symfony\Component\Validator\Constraints as Assert;
use App\Repository\ConfigurationRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=ConfigurationRepository::class)
 */
class GlobalPropertyAttribute
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     * @Assert\Type(type="numeric", message=" id is not valid")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=50)
     */
    private $propertyKey;

    /**
     * @ORM\Column(type="json")
     */
    private $propertyValue = [];

    /**
     * @ORM\Column(type="string", length=10)
     */
    private $scope;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $description;

    /**
     * @ORM\ManyToOne(targetEntity=User::class, inversedBy="globalPropertyAttributes")
     */
    private $user;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPropertyKey(): ?string
    {
        return $this->propertyKey;
    }

    public function setPropertyKey(string $propertyKey): self
    {
        $this->propertyKey = $propertyKey;

        return $this;
    }

    /**
     * @return array
     */
    public function getPropertyValue(): array
    {
        return $this->propertyValue;
    }

    /**
     * @param array $propertyValue
     */
    public function setPropertyValue(array $propertyValue): void
    {
        $this->propertyValue = $propertyValue;
    }

    public function setValue(string $value){
        $this->propertyValue[] = $value;
    }

    public function rmvValue(string $value){
        unset($this->propertyValue[array_search($value, $this->propertyValue)]);
        sort($this->propertyValue);
    }

    public function hasValue(string $value) :bool {
        return array_search($value, $this->propertyValue) !== false;
    }

    public function getScope(): ?string
    {
        return $this->scope;
    }

    public function setScope(string $scope): self
    {
        $this->scope = $scope;

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

    public function getUser(): ?user
    {
        return $this->user;
    }

    public function setUser(?user $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function serialize() {
        $data = [
            "id"=>$this->id,
            "scope"=>$this->scope,
        ];

        $dataValue=[];
       foreach($this->propertyValue as $value){
           array_push($dataValue, $value);
       }
       $data["propertyValue"] = $dataValue;
        return $data;
    }
}
