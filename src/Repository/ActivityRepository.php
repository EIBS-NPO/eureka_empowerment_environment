<?php

namespace App\Repository;

use App\Entity\Activity;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
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

    public function search($criterias){
        $qb = $this->createQueryBuilder('a');
        if(isset($criterias["creator_id"])
            || isset($criterias["creator_firstname"])
            || isset($criterias["creator_lastname"])
            || isset($criterias["creator_email"])
        )$qb ->join('a.creator', 'c');

        if(isset($criterias["project_id"])
            || isset($criterias["project_title"])
        )$qb ->join('a.project', 'p');

        if(isset($criterias["organization_id"])
            || isset($criterias["organization_name"])
            || isset($criterias["organization_email"])
        )$qb ->join('a.organization', 'o');


        foreach($criterias as $key => $value){
            if(preg_match('(^creator_id$|^creator_firstname$|^creator_lastname$|^creator_email$)', $key)) {
                $prefix = "c.";
                $keylike = explode("_", $key)[1];
            }else if(preg_match('(^project_id$|^project_title$)', $key)){
                $prefix = "p.";
                $keylike = explode("_", $key)[1];
            }
            else if(preg_match('(^organization_id$|^organization_name$|^organization_email$)', $key)){
                $prefix = "o.";
                $keylike = explode("_", $key)[1];
            }
            else {
                $prefix = "a.";
                $keylike = $key;
            }

            $qb->andWhere($prefix.$keylike.' LIKE :'.$keylike)
                ->setParameter($keylike, '%'.$value.'%');
        }
        return $qb->getQuery()->getResult();
    }
}
