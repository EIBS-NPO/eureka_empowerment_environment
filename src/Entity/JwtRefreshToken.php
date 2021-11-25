<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Repository\RefreshTokenRepository;
use Gesdinet\JWTRefreshTokenBundle\Entity\RefreshToken;

/**
 * This class extends Gesdinet\JWTRefreshTokenBundle\Entity\RefreshToken to have another table name.
 *
 * @ORM\Entity(repositoryClass=RefreshTokenRepository::class)
 */
class JwtRefreshToken extends RefreshToken
{

    /**
     * @ORM\Column(type="boolean")
     */
    protected bool $emailConfirm = false;

    /**
     * @ORM\Column(type="boolean")
     */
    protected bool $changePassword = false;

    /**
     * @return bool
     */
    public function isEmailConfirm(): bool
    {
        return $this->emailConfirm;
    }

    /**
     * @param bool $emailConfirm
     */
    public function setEmailConfirm(bool $emailConfirm): void
    {
        $this->emailConfirm = $emailConfirm;
    }

    /**
     * @return bool
     */
    public function isChangePassword(): bool
    {
        return $this->changePassword;
    }

    /**
     * @param bool $changePassword
     */
    public function setChangePassword(bool $changePassword): void
    {
        $this->changePassword = $changePassword;
    }
}