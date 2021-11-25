<?php

namespace App\Entity\Interfaces;

use App\Entity\Address;

interface AddressableObject
{
    public function getAddress() : ?Address;

    public function setAddress(?Address $address) : self;
}