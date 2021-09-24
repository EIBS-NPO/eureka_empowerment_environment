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
}