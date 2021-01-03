<?php

namespace App\Controller;

use Lexik\Bundle\JWTAuthenticationBundle\Security\User\JWTUserInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use App\Entity\User;

class SecurityController extends AbstractController
{
    /**
     * @Route("/api/login_check", name="/api/login")
     * @return JsonResponse
     */
    public function login(): JsonResponse
    {
        //todo token non stocké, utilisateur non retenu...!
        return new JsonResponse([
            "message" =>"connected"
        ]);
       /* return new JsonResponse(['token' => $JWTManager->create($user)]);*/
    }

    /*public function getTokenUser(UserInterface $user, JWTTokenManagerInterface $JWTManager)
    {
        // ...

        return new JsonResponse(['token' => $JWTManager->create($user)]);
    }*/
}
