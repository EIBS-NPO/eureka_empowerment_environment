<?php

namespace App\Repository;

use App\Entity\Organization;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Organization|null find($id, $lockMode = null, $lockVersion = null)
 * @method Organization|null findOneBy(array $criteria, array $orderBy = null)
 * @method Organization[]    findAll()
 * @method Organization[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class OrganizationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Organization::class);
    }

    public function findAssigned($userId){
        return $this->createQueryBuilder('o')
            ->join('o.membership', 'm' )
            ->andWhere('m.id = :userId')
            ->setParameter('userId', $userId)
            ->getQuery()
            ->getResult()
            ;
    }

    public function findAssignedById($userId, $orgId){
        return $this->createQueryBuilder('o')
            ->join('o.membership', 'm' )
            ->andWhere('m.id = :userId AND o.id = :orgId')
            ->setParameter('userId', $userId)
            ->setParameter('orgId', $orgId)
            ->getQuery()
            ->getResult()
            ;
    }


    //todo no follow-up relationship on organizations yet
    public function findFollowed($userId){
        return $this->createQueryBuilder('o')
            ->join('o.followings', 'f' )
            ->andWhere('f.follower = :userId AND f.isFollowing = true')
            ->setParameter('userId', $userId)
            ->getQuery()
            ->getResult()
            ;
    }

    //todo no follow-up relationship on organizations yet
    public function findFollowedById($userId, $orgId){
        return $this->createQueryBuilder('o')
            ->join('o.followings', 'f' )
            ->andWhere('f.follower = :userId AND f.isFollowing = true AND o.id = :orgId')
            ->setParameter('userId', $userId)
            ->setParameter('orgId', $orgId)
            ->getQuery()
            ->getResult()
            ;
    }

    public function search($criterias){
        $qb = $this->createQueryBuilder('o');
        if(isset($criterias["referent_id"])
            || isset($criterias["referent_firstname"])
            || isset($criterias["referent_lastname"])
            || isset($criterias["referent_email"])
        )$qb ->join('o.referent', 'r');

        foreach($criterias as $key => $value){
            if(preg_match('(^referent_id$|^referent_firstname$|^referent_lastname$|^referent_email$)', $key)) {
                $prefix = "r.";
                $keylike = explode("_", $key)[1];
            }else {
                $prefix = "o.";
                $keylike = $key;
            }

            $qb->orWhere($prefix.$keylike.' LIKE :'.$keylike)
                ->setParameter($keylike, '%'.$value.'%');
        }
        return $qb->getQuery()->getResult();
    }

    // /**
    //  * @return Organization[] Returns an array of Organization objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('o.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?Organization
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
