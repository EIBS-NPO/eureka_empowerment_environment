<?php

namespace App\Entity\Interfaces;


//use App\Entity\FollowingProject;

/**
 * Interface TrackableObject
 * A Trackable object can be followed or/and assigned.
 * @package App\Entity\Interfaces
 */
interface PictorialObject
{
    public function getPicturePath(): ?string;

    public function setPicturePath(?string $picturePath): self;

    public function getPictureFile() :?String;

    public function setPictureFile($pictureFile): self;
}