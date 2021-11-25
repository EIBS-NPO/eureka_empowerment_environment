<?php

namespace App\EventListener;

use App\Repository\RefreshTokenRepository;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;

class JWTCreatedListener
{

    private RefreshTokenRepository $refreshRepo;

    public function __construct(RefreshTokenRepository $refreshRepo){
        $this->refreshRepo = $refreshRepo;
    }

    /**
     * Replaces the data in the generated
     *
     * @param JWTCreatedEvent $event
     *
     * @return void
     */
    public function onJWTCreated(JWTCreatedEvent $event)
    {
        $user = $event->getUser();

        //delete oldInvalidRefreshToken
        $this->refreshRepo->deletePreviousRefreshToken($user->getUsername());

        $data = $event->getData();
        $payload  =
            [
                'id' => $user->getId(),
                'firstname' => $user->getFirstname(),
                'lastname' => $user->getLastname(),
                'email' => $user->getEmail()
            ];
        $event->setData(array_merge($data,$payload));
    }
}