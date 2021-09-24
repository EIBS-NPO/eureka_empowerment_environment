<?php


namespace App\Entity\Interfaces;


use App\Entity\FollowingProject;

/**
 * Interface TrackableObject
 * A Trackable object can be followed or/and assigned.
 * @package App\Entity\Interfaces
 */
interface TrackableObject
{
    public function getFollowings();
    public function getCreator();

    public function addFollowing(FollowingProject $following);
    public function removeFollowing(FollowingProject $following);
}