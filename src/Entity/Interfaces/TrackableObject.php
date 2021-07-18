<?php


namespace App\Entity\Interfaces;


use App\Entity\Following;

/**
 * Interface TrackableObject
 * A Trackable object can be followed or/and assigned.
 * @package App\Entity\Interfaces
 */
interface TrackableObject
{
    public function getFollowings();
    public function getCreator();
    //todo change followingNme, need more generic
    //just following?
    public function addFollowing(Following $following);
    public function removeFollowing(Following $following);
}