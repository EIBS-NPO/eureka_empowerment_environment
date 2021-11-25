<?php

namespace App\Services\Security;


use App\Exceptions\NoFoundException;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class SecurityHandler
{

    private JWTTokenManagerInterface $JWTManager;
    private UserPasswordEncoderInterface $encoder;

    /**
     * @param JWTTokenManagerInterface $JWTManager
     * @param UserPasswordEncoderInterface $encoder
     */
    public function __construct(JWTTokenManagerInterface $JWTManager, UserPasswordEncoderInterface $encoder)
    {
        $this->JWTManager = $JWTManager;
        $this->encoder = $encoder;
    }

    public function createToken(UserInterface $user): String{
        return $this->JWTManager->create($user);
    }

    public function hashPassword(UserInterface $user):String {
        return $this->encoder->encodePassword($user, $user->getPassword());
    }

}