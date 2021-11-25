<?php

namespace App\Repository;

use App\Entity\JwtRefreshToken;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method JwtRefreshToken|null find($id, $lockMode = null, $lockVersion = null)
 * @method JwtRefreshToken|null findOneBy(array $criteria, array $orderBy = null)
 * @method JwtRefreshToken[]    findAll()
 * @method JwtRefreshToken[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class RefreshTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, JwtRefreshToken::class);
    }

    public function deletePreviousRefreshToken($username){

        return $this->createQueryBuilder('r')
            ->delete()
            ->andWhere('r.username = :email')
            ->setParameter(':email', $username)
            ->getQuery()
            ->getResult()
            ;

        /*return $this->createQueryBuilder('r')
            ->delete()
            ->andWhere('r.username = :email')
            ->andWhere('r.valid < :now')
            ->setParameter(':email', $username)
            ->setParameter(':now', new \DateTime('now'))
            ->getQuery()
            ->getResult()
            ;*/
    }
}