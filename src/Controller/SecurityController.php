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
     * @Route("/security/login", name="login")
     * @return JsonResponse
     */
    public function login(): JsonResponse
    {

       // return new JsonResponse(['token' => $JWTManager->create($user)]);
    }

    /*public function getTokenUser(UserInterface $user, JWTTokenManagerInterface $JWTManager)
    {
        // ...

        return new JsonResponse(['token' => $JWTManager->create($user)]);
    }*/
}
