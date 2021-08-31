<?php

namespace App\Controller;

use App\Entity\User;
use App\Exceptions\SecurityException;
use App\Exceptions\ViolationException;
use App\Service\FileHandler;
use App\Service\LogService;
use App\Service\Request\ParametersValidator;
use App\Service\Request\RequestParameters;
use App\Service\Request\ResponseHandler;
use App\Service\Security\RequestSecurity;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

/**
 * Class UserController
 * @package App\Controller
 * @Route("/user", name="user")
 */
class UserController extends AbstractController
{
    private RequestSecurity $security;
    private RequestParameters $parameters;
    private ResponseHandler $responseHandler;
    private ParametersValidator $validator;
    protected EntityManagerInterface $entityManager;
    protected FileHandler $fileHandler;
    private LogService $logger;

    /**
     * UserController constructor.
     * @param RequestParameters $requestParameters
     * @param ResponseHandler $responseHandler
     * @param ParametersValidator $validator
     * @param EntityManagerInterface $entityManager
     * @param FileHandler $fileHandler
     * @param LogService $logger
     */
    public function __construct(RequestParameters $requestParameters, ResponseHandler $responseHandler, ParametersValidator $validator, EntityManagerInterface $entityManager, FileHandler $fileHandler, LogService $logger)
    {
        $this->parameters = $requestParameters;
        $this->responseHandler = $responseHandler;
        $this->validator = $validator;
        $this->entityManager = $entityManager;
        $this->fileHandler = $fileHandler;
        $this->logger = $logger;
    }


    /**
     * @Route("/register", name="_registration", methods="post")
     * @param Request $request
     * @param UserPasswordEncoderInterface $encoder
     * @return Response
     */
    public function register(Request $request, UserPasswordEncoderInterface $encoder): Response
    {
       // recover all data's request
        $this->parameters->setData($request);

        //check params Validations
        try{ $this->validator->isInvalid(
                ["email", "firstname", "lastname", "password"],
                ["phone", "mobile"],
                User::class);
        } catch(ViolationException $e){
            $this->logger->logError($e, $this->getUser(), "error");
            return $this->responseHandler->BadRequestResponse($e->getViolationsList());
        }

        //create user object && set validated fields
        $user = new User();
        foreach( ["email", "firstname", "lastname", "password", "phone", "mobile"]
            as $field ) {
                if($this->parameters->getData($field) !== false ) {
                    $setter = 'set'.ucfirst($field);
                    $user->$setter($this->parameters->getData($field));
            }
        }

        //hash password
        $hash = $encoder->encodePassword($user, $user->getPassword());
        $user->setPassword($hash);
        //initiate role USER
        $user->setRoles(["ROLE_USER"]);

        //persist the new user
        try{
            $this->entityManager->persist($user);
            $this->entityManager->flush();
        }catch(Exception $e){
            $this->logger->logError($e,$this->getUser(),"error");
            return $this->responseHandler->serverErrorResponse($e, "An error occured");
        }

        //final response
        return $this->responseHandler->successResponse([$user]);
    }

    /**
     * return user's data
     * need param "all" for all user's data
     * or "id" for a usr by id
     * if no parameter is given, the current user is return
     * @Route("", name="_get", methods="get")
     * @param Request $request
     * @return Response
     */
    public function getUsers(Request $request): Response
    {
        // recover all data's request
        $this->parameters->setData($request);

        $repository = $this->entityManager->getRepository(User::class);

        $criterias = [];
        if(!$this->parameters->getData("all")){
            if (!$this->parameters->getData("id")){
                //default query for current user
                $criterias["id"] = $this->getUser()->getId();
            }else {
                $criterias["id"] =  $this->parameters->getData("id");
            }

        }
        try{
            if(isset($criterias['id'])){
                $dataResponse = $repository->findBy($criterias);
            }else {
                $dataResponse = $repository->findAll();
            }
        }catch(Exception $e){
            $this->logger->logError($e,$this->getUser(),"error" );
            return $this->responseHandler->serverErrorResponse($e, "An error occured");
        }

        //download picture
        foreach($dataResponse as $key => $user){
            try{
                $dataResponse[$key] = $this->fileHandler->loadPicture($user);
            }catch(Exception $e){
                $this->logger->logError($e, $this->getUser(), "error");
                return $this->responseHandler->serverErrorResponse($e, "An error occured");
            }
        }

        //final response
        return $this->responseHandler->successResponse($dataResponse);
    }

    /**
     * if id param, the current user must be admin
     * else no required params, id set by current User
     * optionnal param are ("firstname", "lastname", "phone", "mobile")
     * @Route("", name="_update", methods="put")
     * @param Request $request
     * @return Response
     */
    public function updateUser(Request $request, JWTTokenManagerInterface $JWTManager) : Response
    {
        // recover all data's request
        $this->parameters->setData($request);

        //check access
       /* if($this->parameters->getData('id') !== false) {
            if ($this->getUser()->getRoles()[0] !== "ROLE_ADMIN") {
                return $this->responseHandler->unauthorizedResponse("unauthorized access");
            }else {
                $criterias["id"] = $this->parameters->getData("id");
            }
        }else {
            $criterias["id"] = $this->getUser()->getId();
        }*/
            if ($this->getUser()->getRoles()[0] === "ROLE_ADMIN") {
                $criterias["id"] = $this->parameters->getData("id");
            }else {
                $criterias["id"] = $this->getUser()->getId();
            }

        //check params Validations
        try{
            $this->validator->isInvalid(
            [],
            ["firstname", "lastname", "phone", "mobile"],
            User::class);
        } catch(ViolationException $e){
            $this->logger->logError($e, $this->getUser(), "error");
            return $this->responseHandler->BadRequestResponse($e->getViolationsList());
        }

        $repository = $this->entityManager->getRepository(User::class);
        try{
            $userData = $repository->findBy($criterias);
            if(!empty($userData)) {
                $user = $userData[0];
                foreach( ["firstname", "lastname", "phone", "mobile"]
                         as $field ) {
                    if($this->parameters->getData($field) !== false ) {
                        $setter = 'set'.ucfirst($field);
                        $user->$setter($this->parameters->getData($field));
                    }
                }

                $this->entityManager->flush();
                $user = $this->fileHandler->loadPicture($user);
                $userData = [$user,
                    'token' => $JWTManager->create($user)
                ];
            }

        }catch(Exception $e){
            $this->logger->logError($e,$this->getUser(),"error" );
            return $this->responseHandler->serverErrorResponse($e, "An error occured");
        }

        //final response
        return $this->responseHandler->successResponse($userData);
    }

    /**
     * @param Request $request
     * @return Response
     * @Route("/picture", name="_picture_put", methods="post")
     */
    public function uploadPicture(Request $request ) :Response {
        // recover all data's request
        $this->parameters->setData($request);

        //check access
        //todo deplace l'admin dans un admin controller
        /*if($this->parameters->getData('id') !== false) {
            if ($this->getUser()->getRoles()[0] !== "ROLE_ADMIN") {
                return $this->responseHandler->unauthorizedResponse("unauthorized access");
            }else {
                $criterias["id"] = $this->parameters->getData("id");
            }
        }else {
            $criterias["id"] = $this->getUser()->getId();
        }*/

        $criterias["id"] = $this->getUser()->getId();


        //check if required params exist
        try{ $this->parameters->hasData(["image"]); }
        catch(ViolationException $e) {
            $this->logger->logError($e, $this->getUser(), "error");
            return $this->responseHandler->BadRequestResponse($e->getViolationsList());
        }

        $repository = $this->entityManager->getRepository(User::class);
        try{
            $userData = $repository->findBy($criterias);
            if(!empty($userData)) {
                $user = $userData[0];

                $oldPic = $user->getPicturePath() ? $user->getPicturePath() : null;

                $fileDir = '/pictures/User';
                $picFile = $this->parameters->getData("image");

                //make unique picturePath
                $user->setPicturePath(uniqid().'_'. $this->fileHandler->getOriginalFilename($picFile).'.'. $picFile->guessExtension());

                //upload
                $this->fileHandler->upload($fileDir, $user->getPicturePath(), $picFile);

                $this->entityManager->flush();
                $user = $this->fileHandler->loadPicture($user);
                $userData = [$user];

                //if a picture already exist, need to remove it
                if($oldPic !== null){
                    $this->logger->logInfo(" User with id " . $user->getId() . " remove old Picture " );
                    $this->fileHandler->removeFile($fileDir.'/'.$oldPic);
                }

            }else {
                $this->logger->logInfo(" User with id : ". $criterias["id"] ." not found " );
                return $this->responseHandler->notFoundResponse();
            }
        }catch(Exception $e){
            $this->logger->logError($e,$this->getUser(),"error" );
            return $this->responseHandler->serverErrorResponse($e, "An error occured ");
        }

        $this->logger->logInfo(" User with id " . $user->getId() . " uploaded Picture " );

        //final response
        return $this->responseHandler->successResponse($userData);
    }

    /**
     * @param Request $request
     * @return Response
     * @("/deletePicture", name="_picture_delete", methods="delete")
     */
    public function deletePicture(Request$request){
        // recover all data's request
        $this->parameters->setData($request);

        //check access
        if($this->parameters->getData('id') !== false) {
            if ($this->getUser()->getRoles()[0] !== "ROLE_ADMIN") {
                return $this->responseHandler->unauthorizedResponse("unauthorized access");
            }else {
                $criterias["id"] = $this->parameters->getData("id");
            }
        }else {
            $criterias["id"] = $this->getUser()->getId();
        }

        $repository = $this->entityManager->getRepository(User::class);
        try{
            $userData = $repository->findBy($criterias);
            if(!empty($userData)) {
                $user = $userData[0];

                if($user->getPicturePath() !== null){
                    $this->fileHandler->removeFile('/pictures/User/' .$user->getPicturePath());
                    $user->setPicturePath(null);

                    $this->entityManager->flush();
                    $userData = [$user];
                }

            }else {
                $this->logger->logInfo(" User with id : ". $criterias["id"] ." not found " );
                return $this->responseHandler->notFoundResponse();
            }
        }catch(Exception $e){
            $this->logger->logError($e,$this->getUser(),"error" );
            return $this->responseHandler->serverErrorResponse($e, "An error occured ");
        }

        $this->logger->logInfo(" User with id " . $user->getId() . " remove old Picture " );

        //final response
        return $this->responseHandler->successResponse($userData);
    }



    /**
     * @param Request $request
     * @param UserPasswordEncoderInterface $encoder
     * @param JWTTokenManagerInterface $JWTManager
     * @return Response
     * @Route("/password", name="_password", methods="post")
     */
    public function resetPassword(Request $request, UserPasswordEncoderInterface $encoder, JWTTokenManagerInterface $JWTManager)
    {
        // recover all data's request
        $this->parameters->setData($request);

        //check access and set $criterias
        if($this->parameters->getData('id') !== false) {
            if ($this->getUser()->getRoles()[0] !== "ROLE_ADMIN") {
                return $this->responseHandler->unauthorizedResponse("unauthorized access");
            }else {
                $criterias["id"] = $this->parameters->getData("id");
            }
        }else {
            $criterias["id"] = $this->getUser()->getId();
        }

        //check if required params exist
        try{ $this->parameters->hasData(["password", "newPassword", "confirmNewPassword"]); }
        catch(ViolationException $e) {
            $this->logger->logError($e, $this->getUser(), "error");
            return $this->responseHandler->BadRequestResponse($e->getViolationsList());
        }

        //check match newPassword and confirmPassword
        if($this->parameters->getData('newPassword') !== $this->parameters->getData('confirmNewPassword')) {
            return $this->responseHandler->BadRequestResponse(["newPassword"=>"not match", "confirmNewPassword"=>"not match"]);
        }

        $repository = $this->entityManager->getRepository(User::class);
        try{
            $userData = $repository->findBy($criterias);
            if(!empty($userData)) {
                $user = $userData[0];

                if($this->getUser()->getRoles()[0] !== "ROLE_ADMIN"){
                    //check valid original password, only if current user isn't admin
                    $hash = $encoder->encodePassword($this->getUser(), $this->parameters->getData('password'));
                    if($user->getPassword() !== $hash) {
                        return $this->responseHandler->BadRequestResponse(["password"=> "not match"]);
                    }
                }

                //check params Validations
                $this->parameters->putData("password", $this->parameters->getData("newPassword"));
                try{
                    $this->validator->isInvalid(
                        [],
                        ["password"],
                        User::class);
                } catch(ViolationException $e){
                    $this->logger->logError($e, $this->getUser(), "error");
                    return $this->responseHandler->BadRequestResponse($e->getViolationsList());
                }

                $hash = $encoder->encodePassword($this->getUser(), $this->parameters->getData('password'));
                $user->setPassword($hash);

                //persist the user with new password
                $this->entityManager->flush();
                $user = $this->fileHandler->loadPicture($user);

                //if password was change for currentUser, need refresh Token
                if($criterias["id"] === $this->getUser()->getId()){
                    $dataResponse = [
                        $user,
                        'token' => $JWTManager->create($user),
                    ];
                }else {
                    $dataResponse = [$user];
                }

            }else {
                $this->logger->logInfo("user with id : ". $criterias["id"] ." not found" );
                return $this->responseHandler->notFoundResponse();
            }
        }catch(Exception $e){
            $this->logger->logError($e,$this->getUser(),"error" );
            return $this->responseHandler->serverErrorResponse($e, "An error occured");
        }

        //final response
        return $this->responseHandler->successResponse($dataResponse);
    }


    /**
     * @Route("/email", name="_reset_email", methods="post")
     * @param Request $request
     * @param JWTTokenManagerInterface $JWTManager
     * @return Response
     */
    public function changeEmail(Request  $request, JWTTokenManagerInterface $JWTManager): Response
    {
        // recover all data's request
        $this->parameters->setData($request);

        //check access
        if($this->parameters->getData('id') !== false) {
            if ($this->getUser()->getRoles()[0] !== "ROLE_ADMIN") {
                return $this->responseHandler->unauthorizedResponse("unauthorized access");
            }else {
                $criterias["id"] = $this->parameters->getData("id");
            }
        }else {
            $criterias["id"] = $this->getUser()->getId();
        }

        //check email validity
        try{
            $this->validator->isInvalid(
                ["email"],
                [],
                User::class);
        } catch(ViolationException $e){
            $this->logger->logError($e, $this->getUser(), "error");
            return $this->responseHandler->BadRequestResponse($e->getViolationsList());
        }

        $repository = $this->entityManager->getRepository(User::class);
        try {
            $userData = $repository->findBy($criterias);
            if (!empty($userData)) {
                $user = $userData[0];
                $user->setEmail($this->parameters->getData('email'));

                $this->entityManager->flush();
                $user = $this->fileHandler->loadPicture($user);

                //if email was change for currentUser, need refresh Token
                if($criterias["id"] === $this->getUser()->getId()){
                    $dataResponse = [
                        $user,
                        'token' => $JWTManager->create($user),
                    ];
                }else {
                    $dataResponse = [$user];
                }

            }else {
                $this->logger->logInfo("user with id : ". $criterias["id"] ." not found" );
                return $this->responseHandler->notFoundResponse();
            }
        }catch(Exception $e){
            $this->logger->logError($e,$this->getUser(),"error" );
            return $this->responseHandler->serverErrorResponse($e, "An error occured");
        }

        return $this->responseHandler->successResponse($dataResponse);
    }
}
