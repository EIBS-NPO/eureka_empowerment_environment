<?php

namespace App\Event\Listener;

//use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;

class JWTCreatedListener
{
    /**
     * Replaces the data in the generated
     *
     * @param JWTCreatedEvent $event
     *
     * @return void
     */
    public function onJWTCreated(JWTCreatedEvent $event)
    {
        /** @var $user ..\Entity\User */
        $user = $event->getUser();

        $isConfirm = true;
        if($user->getActivationToken() !== null){
           $isConfirm = false;
        }

        // merge with existing event data
        $payload = array_merge(
            $event->getData(),
            [
                'id' => $user->getId(),
                'firstname' => $user->getFirstname(),
                'lastname' => $user->getLastname(),
                'isConfirm' => $isConfirm
            ]
        );

        $event->setData($payload);
    }
}