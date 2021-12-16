<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method User|null find($id, $lockMode = null, $lockVersion = null)
 * @method User|null findOneBy(array $criteria, array $orderBy = null)
 * @method User[]    findAll()
 * @method User[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function findByActivationToken($token){
        return $this->createQueryBuilder('u')
            ->join('u.globalPropertyAttributes', 'g' )
            ->andWhere('g.propertyKey = :key')
            ->andWhere('g.propertyValue = :token')
            ->setParameter(':key', "user.token.activation")
            ->setParameter(':token', "[\"".$token."\"]")
            ->getQuery()
            ->getResult()
            ;
    }

    public function findAllUnconfirmed(){
        return $this->createQueryBuilder('u')
            ->join('u.globalPropertyAttributes', 'g' )
            ->andWhere('g.propertyKey = :key')
            ->setParameter(':key', "user.token.activation")
            ->getQuery()
            ->getResult()
            ;
    }

    public function findAllDisabled(){
        return $this->createQueryBuilder('u')
            ->andWhere('u.roles = :roles')
            ->setParameter(':roles', "[\"\"]")
            ->getQuery()
            ->getResult()
            ;
    }

    public function findByResetPasswordToken($token){
        return $this->createQueryBuilder('u')
            ->join('u.globalPropertyAttributes', 'g' )
            ->andWhere('g.propertyKey = :key')
            ->andWhere('g.propertyValue = :token')
            ->setParameter(':key', "user.token.resetPassword")
            ->setParameter(':token', "[\"".$token."\"]")
            ->getQuery()
            ->getResult()
            ;
    }

   /* public function findFollowersByProject($projectId){
        return $this->createQueryBuilder('u')
            ->join('u.followingProjects', 'flwgP')
            ->andWhere('flwgP.object = :projectId')
            ->andWhere('flwgP.isFollowing = :isFollowing')
            ->setParameter(':projectId', $projectId)
            ->setParameter(':isFollowing', true)
            ->getQuery()
            ->getResult()
            ;
    }*/

//flwgActivity
    public function search($criterias){

        $qb = $this->createQueryBuilder('u');

        if(isset($criterias["followingActivity_id"])) $qb ->join('u.followingActivities', 'flwgA');
        if(isset($criterias["followingProject_object"])) $qb ->join('u.followingProjects', 'flwgP');
        if(isset($criterias["memberOf_id"])) $qb ->join('u.memberOf', 'memberO');

        foreach($criterias as $key => $value){

            $orWhere = true;
            $setIdParam = false;

//todo switch more efficient?

            if(preg_match('(^id$)', $key)){
                $setIdParam = true;
            }

            if(preg_match('(^followingActivity_id$)', $key)) {
                $prefix = "flwgA.";
                $setIdParam = true;
                $keylike = explode("_", $key)[1];
            }
            else if(preg_match('(^followingProject_object$)', $key)) {
                $orWhere = false;
                $prefix = "flwgP.";
                $setIdParam = true;
                $keylike = explode("_", $key)[1];

            }
            else if (preg_match( '(^followingProject_isFollowing$)', $key)){
                $orWhere = false;
                $prefix = "flwgP.";
                $keylike = explode("_", $key)[1];
            }
            else if (preg_match( '(^followingProject_isAssigning$)', $key)){
                $orWhere = false;
                $prefix = "flwgP.";
                $keylike = explode("_", $key)[1];
            }
            else if(preg_match('(^memberOf_id$)', $key)){
                $orWhere = false;
                $prefix = "memberO.";
                $setIdParam = true;
                $keylike = explode("_", $key)[1];
              //  $keylike = "id";
            }
            else{
                $prefix = "u.";
                $keylike = $key;
            }

            $setIdParam ? $like = "="
                :  $like = "LIKE";

            $orWhere ? $qb->orWhere($prefix.$keylike.' '.$like.' :'.$keylike)
                : $qb->andWhere($prefix.$keylike.' '.$like.' :'.$keylike);


            $setIdParam ? $qb->setParameter($keylike, $value)
                : $qb->setParameter($keylike, '%'.$value.'%');
        }

    //    dd($qb->getDQL());
        return $qb->getQuery()->getResult();
    }

/*
 * "SELECT u
 * FROM App\Entity\User u
 * INNER JOIN u.followingProjects flwgP
 * INNER JOIN u.followingProjects.object obj
 * WHERE obj.id LIKE :id AND flwgP.isFollowing LIKE :isFollowing"
 */
    // /**
    //  * @return User[] Returns an array of User objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('u.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?User
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
