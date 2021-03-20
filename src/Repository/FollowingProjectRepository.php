<?php

namespace App\Repository;

use App\Entity\FollowingProject;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method FollowingProject|null find($id, $lockMode = null, $lockVersion = null)
 * @method FollowingProject|null findOneBy(array $criteria, array $orderBy = null)
 * @method FollowingProject[]    findAll()
 * @method FollowingProject[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class FollowingProjectRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FollowingProject::class);
    }

    public function getTeammate($id){
        return $this->createQueryBuilder('fp')
            ->select('fp.follower')
            ->andWhere('fp.id = :id')
            ->andWhere('fp.isAssigned = true')
            ->setParameter('id', $id)
            ->getQuery()
            ->getResult();
    }

    // /**
    //  * @return FollowingProject[] Returns an array of FollowingProject objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('f')
            ->andWhere('f.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('f.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?FollowingProject
    {
        return $this->createQueryBuilder('f')
            ->andWhere('f.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
