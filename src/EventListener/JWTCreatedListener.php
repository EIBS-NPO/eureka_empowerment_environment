<?php

namespace App\EventListener;

use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;

class JWTCreatedListener
{

 //   private JWTTokenManagerInterface $jwtManager;

    /*
     * @param JWTTokenManagerInterface $jwtManager
     */
    /*public function __construct(JWTTokenManagerInterface $jwtManager)
    {
        $this->jwtManager = $jwtManager;
    }*/

    /**
     * Replaces the data in the generated
     *
     * @param JWTCreatedEvent $event
     *
     * @return void
     */
    public function onJWTCreated(JWTCreatedEvent $event )
    {
        $user = $event->getUser();

    /*    $isConfirm = true;
        if($user->getActivationToken() !== null){
            $isConfirm = false;
        }*/

        $data = $event->getData();
    //    $data["token"]  = $this->jwtHandler->create($event->getUser());
        $payload  =
            [
                'id' => $user->getId(),
                'firstname' => $user->getFirstname(),
                'lastname' => $user->getLastname()
            ];
        $event->setData(array_merge($data,$payload));
        /*if($user !== null) {
            $isConfirm = true;
            if ($user->getActivationToken() !== null) {
                $isConfirm = false;
            }
            $this->jwtManager->createFromPayload(
               $user,
                [
                    'id' => $user->getId(),
                    'firstname' => $user->getFirstname(),
                    'lastname' => $user->getLastname(),
                    'isConfirm' => $isConfirm
                ]
            );
        }*/
      //  $this->jwtHandler->extendPayload($event);
    }
}