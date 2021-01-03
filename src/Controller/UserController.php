<?php

namespace App\Controller;

use App\Entity\User;
use App\Exceptions\SecurityException;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use App\Service\Security\RequestSecurity;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;

class UserController extends AbstractController
{

    private RequestSecurity $requestSecurity;

    public function __construct(RequestSecurity $requestSecurity){
        $this->requestSecurity = $requestSecurity;
    }

    /**
     * @Route("/register", name="register", methods="post")
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @param UserPasswordEncoderInterface $encoder
     * @return JsonResponse
     */
    public function register(Request $request, EntityManagerInterface $entityManager, UserPasswordEncoderInterface $encoder): JsonResponse
    {
        try{
            $request = $this->requestSecurity->cleanXSS($request);
        }catch(SecurityException $exception){
            return new JsonResponse(
                ["success" => false, "error" => "Potential attack has been detected"],
                403,
                ["Content-Type" => "application/json"]
            );
        }

        $user = new User();
        $userData = json_decode($request->getContent());
        try{
            $user->setFirstname($userData->firstname);
            $user->setLastname($userData->lastname);
            $user->setEmail($userData->email);
            $user->setRoles((array)$userData->roles);

            $user->setPassword($userData->password);
            $hash = $encoder->encodePassword($user, $user->getPassword());
            $user->setPassword($hash);
        }catch(\Exception $e){
            return new JsonResponse(
                ["success" => false, "error" => $e->getMessage()],
                $e->getCode(),
                ["Content-Type" => "application/json"]
            );
        }

        try{
            $entityManager->persist($user);
            $entityManager->flush();
        }catch(\Exception $e){
            return new JsonResponse(
                ["success" => false, "error" => $e->getMessage()],
                $e->getCode(),
                ["Content-Type" => "application/json"]
            );
        }


        $serializer = $this->get('serializer');
        $user = $serializer->serialize($user, 'json');

        return new JsonResponse(
            $user,
            201,
            ["Content-Type" => "application/json"],
            true
        );
    }

    /**
     * @Route("/api/user/{id}", methods="get")
     * @param UserRepository $userRepository
     * @return JsonResponse
     */
    public function getUserById(int $id, UserRepository $userRepository): JsonResponse
    {
        $user = $userRepository->findBy(['id'=> $id])[0];

        $serializer = $this->get('serializer');
        $user = $serializer->serialize($user, 'json');

        return JsonResponse::fromJsonString($user);
    }

    /**
     * @Route("/api/users", methods="get")
     * @param UserRepository $userRepository
     * @return JsonResponse
     */
    public function getUsers(UserRepository $userRepository) :JsonResponse{
        $users = $userRepository->findAll();

        $serializer = $this->get('serializer');
        $users = $serializer->serialize($users, 'json');

        return JsonResponse::fromJsonString($users);
    }

    /**
     * @Route("api/admin/user/{id}/delete", methods="delete")
     * @param int $id
     * @param EntityManagerInterface $entityManager
     * @return JsonResponse
     */
    public function deleteUser(int $id, EntityManagerInterface $entityManager):JsonResponse
    {
        $data = ["message" => "User with id $id delete success"];
        $status = 200;

        try{
            $user = $entityManager->getRepository(User::class)->find($id);
            /*if (!$user) {
                $data = ["message" => "User not found with id " . $id];
                $status = 404;

                //throw $this->createNotFoundException("User not found with id " . $id);
            }
            else{*/
            //todo la levé d'exception ne gère pas le code???
                try{
                    $entityManager->remove($user);
                    $entityManager->flush();
                }catch (\Exception $e){
                    /*$data = $e->getMessage();
                    $status = $e->getCode();*/
                    return new JsonResponse($e);
                }
           // }

        }catch (\Exception $e){
            /*$data = $e->getMessage();
            $status = $e->getCode();*/
            return new JsonResponse($e);
        }

        return new JsonResponse($data, $status);
    }

    /**
     * @param mixed $data    The response data
     * @param int   $status  The response status code
     * @param array $headers An array of response headers
     * @param bool  $json    If the data is already a JSON string
     */
    /*public function __construct($data = null, int $status = 200, array $headers = [], bool $json = false)*/

    /**
     * @Route("api/user/{id}/update", methods="patch")
     * @param int $id
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return JsonResponse
     */
    public function updateUser(int $id, Request $request, EntityManagerInterface $entityManager) : JsonResponse
    {
        $user = $entityManager->getRepository(User::class)->find($id);
        if (!$user) {throw $this->createNotFoundException("User not found with id " . $id);}

        $userData = json_decode($request->getContent());
        foreach($userData as $key => $data){
            $setter = "set".ucfirst($key);
            $user->$setter($data);
        }

        $entityManager->persist($user);
        $entityManager->flush();

        $serializer = $this->get('serializer');
        $user = $serializer->serialize($user, 'json');

        return JsonResponse::fromJsonString($user);
    }
}
