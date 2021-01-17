<?php

namespace App\Controller;

use App\Entity\User;
use App\Exceptions\SecurityException;
use App\Service\Request\ParametersValidator;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\ORMException;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use App\Service\Security\RequestSecurity;
use App\Service\Request\RequestParameters;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Class UserController
 * @package App\Controller
 */
class UserController extends AbstractController
{
    private RequestSecurity $requestSecurity;

    private RequestParameters $requestParameters;

    private EntityManagerInterface $entityManager;

    private ParametersValidator $paramValidator;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * UserController constructor.
     * @param RequestSecurity $requestSecurity
     * @param RequestParameters $requestParameters
     * @param EntityManagerInterface $entityManager
     * @param ParametersValidator $paramValidator
     * @param LoggerInterface $logger
     */
    public function __construct(RequestSecurity $requestSecurity, RequestParameters $requestParameters, EntityManagerInterface $entityManager, ParametersValidator $paramValidator, LoggerInterface $logger){
        $this->requestSecurity = $requestSecurity;
        $this->requestParameters = $requestParameters;
        $this->paramValidator = $paramValidator;
        $this->entityManager = $entityManager;
        $this->logger = $logger;
    }

    /**
     * @Route("/user/register", name="register", methods="post")
     * @param Request $request
     * @param UserPasswordEncoderInterface $encoder
     * @return Response
     */
    public function register(Request $request, UserPasswordEncoderInterface $encoder): Response
    {
        try{
            $request = $this->requestSecurity->cleanXSS($request);
        }catch(SecurityException $e){
            $this->logger->warning($e);
            return new Response(
                json_encode(["error" => "ACCESS_FORBIDDEN"]),
                Response::HTTP_FORBIDDEN,
                ["Content-Type" => "application/json"]);
        }

        //place all parameters of the request in an array $data
        $data = $this->requestParameters->getData($request);

        //create user object
        $user = new User();

        //Validate fields
     //   $this->paramValidator->initValidator($user, $data);
        $this->paramValidator->initValidator(["email","firstname", "lastname", "password"],null,User::class, $data);
        try{
            $violationsList = $this->paramValidator->checkViolations();

            //return violations //todo simple message for the front?
            if( count($violationsList) > 0 ){
                return new Response(
                    json_encode(["error" => $violationsList]),
                    Response::HTTP_BAD_REQUEST,
                    ["Content-Type" => "application/json"]
                );
            }
        }catch(Exception $e){
            $this->logger->Error($e->getMessage());
            return new Response(
                json_encode(["error" => "SERVER_ERROR"]),
                $e->getCode(),
                ["Content-Type" => "application/json"]
            );
        }

        //set user's validated fields
        $user->setFirstname($data['firstname']);
        $user->setLastname($data['lastname']);
        $user->setEmail($data['email']);
        $user->setPassword($data['password']);

        //hash password
        $hash = $encoder->encodePassword($user, $user->getPassword());
        $user->setPassword($hash);

        $user->setRoles(["ROLE_USER"]);

        //persist the new user
        try{
            $this->entityManager->persist($user);
            $this->entityManager->flush();
        }catch(Exception $e){
            $this->logger->warning($e);
            return new Response(
                json_encode(["error" => "ERROR_SERVER"]),
                $e->getCode(),
                ["Content-Type" => "application/json"]
            );
        }

        //success
        $this->logger->info("new User registerd with id : " .$user->getId());
        return new Response(
            json_encode(["success" => true]),
            Response::HTTP_OK,
            ["Content-Type" => "application/json"]
        );
    }

    /**
     * @Route("/user", name="getUserProfile", methods="get")
     * @param Request $request
     * @return Response
     */
    public function getUserProfile(Request $request): Response
    {
        //cleanXSS
        try {
            $request = $this->requestSecurity->cleanXSS($request);
        }catch(\Exception $e) {
            $this->logger->warning($e);
            return new Response(
                json_encode(["error" => "ACCESS_FORBIDDEN"]),
                Response::HTTP_FORBIDDEN,
                ["Content-Type" => "application/json"]);
        }

        $userRepository = $this->entityManager->getRepository(User::class);
        try{
            $userData = $userRepository->findBy(["id" => $this->getUser()->getId()]);
        }
        catch(\Exception $e){
            $this->logger->error($e);
            return new Response(
                json_encode(["error" => "ERROR_SERVER"]),
                $e->getCode(),
                ["Content-Type" => "application/json"]
            );
        }

        //serialize found userObject
        if (count($userData) > 0 ) {
            $userData = $userData[0]->serialize();
        }
        else {
            $this->logger->error(Response::HTTP_NOT_FOUND . " | User with " . $this->getUser()->getId() . " Not found.");
            return new Response(
                json_encode(["error" => "User Not Found"]),
                Response::HTTP_NOT_FOUND,
                ["Content-Type" => "application/json"]
            );
        }

        return new Response(
            json_encode(["data" => $userData]),
            Response::HTTP_OK,
            ["content-type" => "application/json"]
        );
    }

    /**
     * @Route("/user/update", methods="put")
     * @param Request $request
     * @return Response
     */
    public function updateUser(Request $request) : Response
    {
        //cleanXSS
        try{
            $request = $this->requestSecurity->cleanXSS($request);
        }catch(SecurityException $e){
            $this->logger->warning($e);
            return new Response(
                json_encode(["error" => "ACCESS_FORBIDDEN"]),
                Response::HTTP_FORBIDDEN,
                ["Content-Type" => "application/json"]);
        }

        //recover parameters of the request in an array $data
        $data = $this->requestParameters->getData($request);

        //validation user's id and recover userObject
        $userRepository = $this->entityManager->getRepository(User::class);
        try{
            $user = $userRepository->find($this->getUser()->getId());
            if ($user == null) {
                $this->logger->error(Response::HTTP_NOT_FOUND . " | User with " . $this->getUser()->getId() . " Not found.");
                return new Response(
                    json_encode(["error" => "user not found"]),
                    Response::HTTP_NOT_FOUND,
                    ["Content-Type" => "application/json"]
                );
            }
        }catch(Exception $e){
            $this->logger->error($e);
            return new Response(
                json_encode(["error" => "ERROR_SERVER"]),
                $e->getCode(),
                ["Content-Type" => "application/json"]
            );
        }

        //validation of optional fields
        $optionalFields = ["email","firstname", "lastname", "phone", "mobile"];
        $this->paramValidator->initValidator(null,$optionalFields,User::class, $data);
        try{
            $violationsList = $this->paramValidator->checkViolations();
        }catch(Exception $e){
            $this->logger->error($e);
            return new Response(
                json_encode(["error" => "ERROR_SERVER"]),
                Response::HTTP_INTERNAL_SERVER_ERROR,
                ["Content-Type" => "application/json"]
            );
        }

        //return violations
        if( count($violationsList) > 0 ){
            return new Response(
                json_encode(["success" => false, "error" => $violationsList]),
                Response::HTTP_BAD_REQUEST,
                ["Content-Type" => "application/json"]
            );
        }

        //set user's validated fields
        foreach($optionalFields as $field) {
            if (isset($data[$field])) {
                $setter = "set" . ucfirst($field);
                $user->$setter($data[$field]);
            }
        }

        //persist updated user
        try{
            $this->entityManager->flush();
        }catch(Exception $e){
            $this->logger->error($e);
            return new Response(
                json_encode(["error" => "ERROR_SERVER"]),
                $e->getCode(),
                ["Content-Type" => "application/json"]
            );
        }

        //success
        $this->logger->info("User with id : " .$user->getId(). " successfully updated");
        return new Response(
            //todo logEvent
            json_encode(["data" => $user->serialize()]),
            Response::HTTP_OK,
            ["Content-Type" => "application/json"]
        );
    }
}
