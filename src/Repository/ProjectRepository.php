<?php

namespace App\Repository;

use App\Entity\Project;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\User\UserInterface;

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
