<?php

namespace App\Services\Entity;

use App\Entity\Address;
use App\Entity\Interfaces\AddressableObject;
use App\Exceptions\ViolationException;
use App\Services\Request\ParametersValidator;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\ORMException;

class AddressHandler {

    private ParametersValidator $validator;
    private EntityManagerInterface $entityManager;

    public function __construct(ParametersValidator $validator, EntityManagerInterface $entityManager)
    {
        $this->validator = $validator;
        $this->entityManager = $entityManager;
    }

    /**
     * return the AddressableObject with a updated Address
     * @param AddressableObject $object
     * @param $params
     * @return AddressableObject
     * @throws ViolationException
     */
    public function putAddress(AddressableObject $object, $params) : AddressableObject
    {
        if($params["address"] === "null") $params["address"] = null;
        if(!is_null($params["address"])){
            $this->validator->isInvalid(
                ["address", "country", "city", "zipCode"],
                ["complement", "latitude", "longitude"],
                Address::class);

            //retrieve or create an Address
            if($object->getAddress() !== null){
                $address = $object->getAddress();
            }else {
                $address = new Address();
            }

            //put attribute for Address
            $address = $this->setAddress($address, $params);
            $address->setOwnerType($this->getClassName($object));
            $object->setAddress($address);
        }
        /*else{ //remove address
            $address = $object->getAddress();
            $this->entityManager->remove($address);
            $object->setAddress(null);
            $this->entityManager->flush();
        }*/

        return $object;
    }

    private function setAddress($address, array $attributes){
        foreach( ["address", "country", "city", "zipCode", "complement", "latitude", "longitude"]
                 as $field ) {
            if(isset($attributes[$field])) {
                if($field === "complement" && $attributes[$field] === "null") $attributes[$field] = null;
                $setter = 'set'.ucfirst($field);
                $address->$setter($attributes[$field]);
            }
        }
        return $address;
    }

    private function getClassName($entity) :String {
        $namespace = explode("\\", get_class($entity));
        return end($namespace);
    }
}