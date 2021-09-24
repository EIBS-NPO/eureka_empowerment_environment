<?php

namespace App\Controller;

use App\Entity\User;
use App\Exceptions\BadMediaFileException;
use App\Exceptions\NoFoundException;
use App\Exceptions\PartialContentException;
use App\Exceptions\ViolationException;
use App\Services\Entity\UserHandler;
use App\Services\FileHandler;
use App\Services\LogService;
use App\Services\Mailer\MailHandler;
use App\Services\Request\ParametersValidator;
use App\Services\Request\RequestParameters;
use App\Services\Request\ResponseHandler;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

/**
 * Class UserController
 * @package App\Controller
 * @Route("/user", name="user")
 */
class UserController extends AbstractController
{
    private RequestParameters $parameters;
    private ResponseHandler $responseHandler;
    private ParametersValidator $validator;
    protected EntityManagerInterface $entityManager;
    protected FileHandler $fileHandler;
    private LogService $logger;
    private UserHandler $userHandler;
    private MailHandler $mailHandler;

    /**
     * UserController constructor.
     * @param RequestParameters $requestParameters
     * @param ResponseHandler $responseHandler
     * @param ParametersValidator $validator
     * @param EntityManagerInterface $entityManager
     * @param UserHandler $userHandler
     * @param FileHandler $fileHandler
     * @param LogService $logger
     * @param MailHandler $mailHandler
     */
    public function __construct(RequestParameters $requestParameters, ResponseHandler $responseHandler, ParametersValidator $validator, EntityManagerInterface $entityManager, UserHandler $userHandler, FileHandler $fileHandler, LogService $logger, MailHandler $mailHandler)
    {
        $this->parameters = $requestParameters;
        $this->responseHandler = $responseHandler;
        $this->validator = $validator;
        $this->entityManager = $entityManager;
        $this->userHandler = $userHandler;
        $this->fileHandler = $fileHandler;
        $this->logger = $logger;
        $this->mailHandler = $mailHandler;
    }


    /**
     * @param Request $request
     * @return Response
     * @Route("/register", name="_registration", methods="POST")
     */
    public function register(Request $request): Response
    {
        try{
            // recover all data's request
            $this->parameters->setData($request);
            $newUser = $this->userHandler->create($this->parameters->getAllData());
            return $this->responseHandler->successResponse([$newUser]);

            //todo sendmail confirm by listener termiate?
        //    $this->mailHandler->sendConfirmEmail($newUser);
        }/*
        catch (PartialContentException $e) {
            $this->logger->logError($e, $this->getUser(), "error");
            return $this->responseHandler->partialResponse( $e, "read_user");
        }*/
        catch (ViolationException $e) {
            $this->logger->logError($e, $this->getUser(), "error");
            return $this->responseHandler->BadRequestResponse($e->getMessage());
        }
        catch(UniqueConstraintViolationException $e){
            $this->logger->logError($e, $this->getUser(), "error");
            return $this->responseHandler->BadRequestResponse(json_encode(["email" => "User's email already exist"]));
        }
        catch(Exception $e){
            $this->logger->logError($e, $this->getUser(), "error");
            return $this->responseHandler->serverErrorResponse("An error occured");
        }
    }

    /**
     * @Route("/sendActivation", name="_ask_activation", methods="post")
     * @throws TransportExceptionInterface
     */
    public function sendAskActivation(Request $request): Response
    {
        try {
            // recover all data's request
            $this->parameters->setData($request);
            $this->parameters->hasData(["email","urlActivation"]);
            $this->userHandler->sendAskActivation(
                $this->parameters->getData("urlActivation"),
                $this->parameters->getData("email")
            );

            return $this->responseHandler->successResponse([]);
        }
        catch(ViolationException | NoFoundException $e) {
            $this->logger->logError($e, null, "error");
            return $this->responseHandler->BadRequestResponse($e->getMessage());
        }
        catch(TransportExceptionInterface | Exception $e){
            $this->logger->logError($e, null, "error");
            return $this->responseHandler->serverErrorResponse("An error occured");
        }
    }

    /**
     * @param Request $request
     * @return Response
     * @Route("/activation", name="_activation", methods="post")
     */
    public function activation(Request $request) :Response {
            try{
                // recover all data's request
                $this->parameters->setData($request);
                $this->parameters->hasData(["token"]);
                $this->userHandler->activation($this->parameters->getData("token"));

                $this->logger->logInfo("USER ACTIVATED");
                return $this->responseHandler->successResponse([]);
            }
            catch(ViolationException | NoFoundException $e) {
                $this->logger->logError($e, null, "error");
                return $this->responseHandler->BadRequestResponse($e->getMessage());
            }
            catch(Exception $e){
                $this->logger->logError($e, $this->getUser(), "error");
                return $this->responseHandler->serverErrorResponse("An error occured");
            }
    }

    /**
     * need "id" for one user, else all returned
     * @Route("", name="_get", methods="get")
     * @param Request $request
     * @return Response
     */
    public function getUsers(Request $request): Response
    {
        try{
            $this->parameters->setData($request);

            $users = $this->userHandler->getUsers($this->getUser(), $this->parameters->getAllData());

            $users = $this->userHandler->withPictures($users);
        //final response
        return $this->responseHandler->successResponse($users);
        }
        catch(ViolationException | NoFoundException $e) {
            $this->logger->logError($e, null, "error");
            return $this->responseHandler->BadRequestResponse($e->getMessage());
        }

    }

    /**
     * update user data for the currentUser
     * optionnal param are ("firstname", "lastname", "phone", "mobile", picture)
     * @Route("", name="_update", methods="put")
     * @param Request $request
     * @param JWTTokenManagerInterface $JWTManager
     * @return Response
     */
    public function updateUser(Request $request, JWTTokenManagerInterface $JWTManager) : Response
    {
        try{
            // recover all data's request
            $this->parameters->setData($request);

            $user = $this->userHandler->getUsers(["id" => $this->getUser()->getId()])[0];

            $user = $this->userHandler->updateUser($user, $this->parameters->getAllData());

            //make newToken with updatedUser and newInfos
            $userData = [$this->userHandler->withPictures([$user])[0],
                'token' => $JWTManager->create($user)
            ];

        return $this->responseHandler->successResponse($userData);
        }
        catch(PartialContentException $e){
            $this->logger->logError($e, $this->getUser(), "error");
            return $this->responseHandler->partialResponse($e);
        }
        catch(ViolationException | NoFoundException $e) {
            $this->logger->logError($e, null, "error");
            return $this->responseHandler->BadRequestResponse($e->getMessage());
        }
        catch (Exception $e) {//unexpected error
            $this->logger->logError($e, $this->getUser(), "error");
            return $this->responseHandler->serverErrorResponse("An error occurred");
        }

    }

    /**
     * @param Request $request
     * @return Response
     * @Route("/picture", name="_picture_put", methods="post")
     */
    public function putPicture(Request $request ) :Response {
        try{
            // recover all data's request
            $this->parameters->setData($request);
            $this->parameters->hasData(["pictureFile"]);

            //force id width current user
            $this->parameters->addParam("id", $this->getUser()->getId());

            //get org by id with owned context and notFoundException
            $user = $this->userHandler->getUsers($this->parameters->getAllData())[0];

            $user = $this->userHandler->putPicture($user, $this->parameters->getAllData());

            $user = $this->userHandler->withPictures([$user]);

        return $this->responseHandler->successResponse($user);
        }
        catch(ViolationException | NoFoundException $e) {
            $this->logger->logError($e, $this->getUser(), "error");
            return $this->responseHandler->BadRequestResponse($e->getMessage());
        }
        catch (BadMediaFileException $e){
            $this->logger->logError($e, $this->getUser(), "error");
            return $this->responseHandler->BadMediaResponse($e->getMessage());
        }
        catch (Exception $e) {//unexpected error
            $this->logger->logError($e, $this->getUser(), "error");
            return $this->responseHandler->serverErrorResponse("An error occurred");
        }
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
