<?php

namespace App\Repository;

use App\Entity\Activity;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Activity|null find($id, $lockMode = null, $lockVersion = null)
 * @method Activity|null findOneBy(array $criteria, array $orderBy = null)
 * @method Activity[]    findAll()
 * @method Activity[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ActivityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Activity::class);
    }

    //todo useless?
    public function getActivityDType($id){
        return $this->createQueryBuilder('qb')
            ->select('qb.dtype')
            ->andWhere('qb.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getResult();
    }

    public function findFollowed($userId){
        return $this->createQueryBuilder('a')
            ->join('a.followers', 'f' )
            ->andWhere('f.id = :userId')
            ->setParameter('userId', $userId)
            ->getQuery()
            ->getResult()
            ;
    }

    public function findFollowedById($userId, $activityId){
        return $this->createQueryBuilder('a')
            ->join('a.followings', 'f' )
            ->andWhere('f.follower = :userId AND a.id = :activityId')
            ->setParameter('userId', $userId)
            ->setParameter('activityId', $activityId)
            ->getQuery()
            ->getResult()
            ;
    }


    // /**
    //  * @return Activity[] Returns an array of Activity objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('a.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?Activity
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
