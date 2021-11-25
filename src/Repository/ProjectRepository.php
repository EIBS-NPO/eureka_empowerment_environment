<?php

namespace App\Repository;

use App\Entity\Project;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Project|null find($id, $lockMode = null, $lockVersion = null)
 * @method Project|null findOneBy(array $criteria, array $orderBy = null)
 * @method Project[]    findAll()
 * @method Project[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ProjectRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Project::class);
    }

    public function findAssigned($userId){
        return $this->createQueryBuilder('p')
            ->join('p.followings', 'f' )
            ->andWhere('f.follower = :userId AND f.isAssigning = true')
            ->setParameter('userId', $userId)
            ->getQuery()
            ->getResult()
            ;
    }

    public function findAssignedById($userId, $projectId){
        return $this->createQueryBuilder('p')
            ->join('p.followings', 'f' )
            ->andWhere('f.follower = :userId AND f.isAssigning = true AND p.id = :projectId')
            ->setParameter('userId', $userId)
            ->setParameter('projectId', $projectId)
            ->getQuery()
            ->getResult()
            ;
    }

    public function findFollowed($userId){
        return $this->createQueryBuilder('p')
            ->join('p.followings', 'f' )
            ->andWhere('f.follower = :userId AND f.isFollowing = true')
            ->setParameter('userId', $userId)
            ->getQuery()
            ->getResult()
            ;
    }

    public function findFollowedById($userId, $projectId){
        return $this->createQueryBuilder('p')
            ->join('p.followings', 'f' )
            ->andWhere('f.follower = :userId AND f.isFollowing = true AND p.id = :projectId')
            ->setParameter('userId', $userId)
            ->setParameter('projectId', $projectId)
            ->getQuery()
            ->getResult()
            ;
    }

    public function search($criterias){
        $qb = $this->createQueryBuilder('p');

        if(isset($criterias["creator_id"])
            || isset($criterias["creator_firstname"])
            || isset($criterias["creator_lastname"])
            || isset($criterias["creator_email"])
        )$qb ->join('p.creator', 'c');

        if(isset($criterias["organization_id"])
            || isset($criterias["organization_name"])
            || isset($criterias["organization_email"])
        )$qb ->join('p.organization', 'o');

        if(isset($criterias["followings_isAssigning"])){
            $qb ->join('p.followings', 'flwing');
            if(isset($criterias["follower_id"])) $qb ->join('flwing.follower', "flwer");
        }

        foreach($criterias as $key => $value) {
            if (preg_match('(^creator_id$|^creator_firstname$|^creator_lastname$|^creator_email$)', $key)) {
                $prefix = "c.";
                $keylike = explode("_", $key)[1];
            } else if (preg_match('(^organization_id$|^organization_name$|^organization_email)', $key)) {
                $prefix = "o.";
                $keylike = explode("_", $key)[1];
            }else if (preg_match('(^followings_isAssigning$)', $key)) {
                $prefix = "flwing.";
                $keylike = explode("_", $key)[1];
            }else if (preg_match('(^follower_id$)', $key)) {
                $prefix = "flwer.";
                $keylike = explode("_", $key)[1];
            }else {
                $prefix = "p.";
                $keylike = $key;
            }

            $qb->orWhere($prefix.$keylike.' LIKE :'.$keylike)
                ->setParameter($keylike, '%'.$value.'%');
        }
/*
        if(isset($params["assigned_followerId"])){
            dd($qb->getDQL());
        }*/

        return $qb->getQuery()->getResult();
    }

    // /**
    //  * @return Project[] Returns an array of Project objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('p.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?Project
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
