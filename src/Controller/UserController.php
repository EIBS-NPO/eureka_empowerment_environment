<?php

namespace App\Controller;

use App\Entity\Activity;
use App\Entity\JwtRefreshToken;
use App\Entity\Organization;
use App\Entity\Project;
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
use DateTime;
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

    /**
     * UserController constructor.
     * @param RequestParameters $requestParameters
     * @param ResponseHandler $responseHandler
     * @param ParametersValidator $validator
     * @param EntityManagerInterface $entityManager
     * @param UserHandler $userHandler
     * @param FileHandler $fileHandler
     * @param LogService $logger
     */
    public function __construct(RequestParameters $requestParameters, ResponseHandler $responseHandler, ParametersValidator $validator, EntityManagerInterface $entityManager, UserHandler $userHandler, FileHandler $fileHandler, LogService $logger)
    {
        $this->parameters = $requestParameters;
        $this->responseHandler = $responseHandler;
        $this->validator = $validator;
        $this->entityManager = $entityManager;
        $this->userHandler = $userHandler;
        $this->fileHandler = $fileHandler;
        $this->logger = $logger;
    }


    /**
     * @param Request $request
     * @return Response
     * @Route("/register", name="_registration", methods="POST")
     */
    public function register(Request $request): Response
    {
        try {
            // recover all data's request
            $this->parameters->setData($request);

            $newUser = $this->userHandler->create($this->parameters->getAllData());
            return $this->responseHandler->successResponse([$newUser]);
        }
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
     * @param Request $request
     * @return Response
     * @Route("/public/activation", name="_get_activation", methods="get")
     */
    public function askActivation(Request $request) : Response {
        try{
            // recover all data's request
            $this->parameters->setData($request);
            $this->parameters->hasData(["email"]);

            $user = $this->entityManager->getRepository(User::class)->findOneBy(["email" => $this->parameters->getData("email")]);
            if(is_null($user)) Throw new NoFoundException("user not found");
         //   $this->userHandler->activation($this->parameters->getData("token"));

            return $this->responseHandler->successResponse([$user]);
        }catch(Exception $e){
            $this->logger->logError($e, $this->getUser(), "error");
            return $this->responseHandler->serverErrorResponse("An error occured");
        }
    }

    /**
     * @param Request $request
     * @return Response
     * @Route("/public/activation", name="_post_activation", methods="post")
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
     * @Route("/public/forgotPassword", name="_forgotPassword", methods="put")
     * @param Request $request
     * @return Response
     */
    public function askForgotPasswordToken(Request $request): Response
    {
        try {
            // recover all data's request
            $this->parameters->setData($request);
            $this->parameters->hasData(["email"]);

        //    $user = $this->userHandler->getUsers(null, ["access" => "search", "email" => $this->parameters->getData("email")]);

            $user = $this->entityManager->getRepository(User::class)->findOneBy(["email" => $this->parameters->getData("email")]);
            if(is_null($user)) throw new NoFoundException("User not found");

            $user = $this->userHandler->add_GPA_resetPassword($user);

            return $this->responseHandler->successResponse([$user]);

        }catch(ViolationException | NoFoundException $e) {
                $this->logger->logError($e, null, "error");
                return $this->responseHandler->BadRequestResponse($e->getMessage());
        }catch(Exception $e){
            $this->logger->logError($e, $this->getUser(), "error");
            return $this->responseHandler->serverErrorResponse("An error occured");
        }
    }

    /**
     * @Route("", name="_get", methods="get")
     * @param Request $request
     * @return Response
     */
    public function getUsers(Request $request): Response
    {
        try{
            $this->parameters->setData($request);

            //check if admin access required
            if($this->parameters->getData("admin")!== false){
                $this->denyAccessUnlessGranted('ROLE_ADMIN');
                $this->parameters->putData("admin", true);
            }

            $users = $this->userHandler->getUsers($this->getUser(), $this->parameters->getAllData(), true);

            $users = $this->userHandler->withPictures($users);
        //final response
        return $this->responseHandler->successResponse($users);
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
     * @Route("/public", name="_getPublic", methods="get")
     * @param Request $request
     * @return Response
     */
    public function getUsersPublic(Request $request): Response
    {
        try{
            $this->parameters->setData($request);

            $users = $this->userHandler->getUsers(null , $this->parameters->getAllData(), true);

            $users = $this->userHandler->withPictures($users);
            //final response
            return $this->responseHandler->successResponse($users);
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
     * update user data for the currentUser
     * optionnal param are ("firstname", "lastname", "phone", "mobile", picture)
     * @Route("/update", name="_update", methods="post")
     * @param Request $request
     * @param JWTTokenManagerInterface $JWTManager
     * @return Response
     */
    public function updateUser(Request $request, JWTTokenManagerInterface $JWTManager) : Response
    {
        try{
            // recover all data's request
            $this->parameters->setData($request);

            //by default force owned access
            $accessTable["access"] = "owned";
            $needNewToken = true; //if userOwned
            //check if admin access required
            if($this->parameters->getData("admin")!== false){
                $this->denyAccessUnlessGranted('ROLE_ADMIN');
                //change accessTable for access by id
                $accessTable["access"] = "search";
                $accessTable["id"] = $this->parameters->getData("id");
                $accessTable["admin"] = true;
                $needNewToken = false; //don't need new token
            }

            $user = $this->userHandler->getUsers(
                $this->getUser(),
                $accessTable,
                true
            )[0];


            //retrieve activity for following relation
            $followActivity = $this->parameters->getData("followActivity");
            if($followActivity !== false && is_numeric($followActivity)){
                $activity = $this->entityManager->getRepository(Activity::class)->find($followActivity);
                if(!is_null($activity)){
                    $this->parameters->putData("followActivity", $activity);
                } else Throw new NoFoundException();
            }

            //retrieve project for following project
            $followProject = $this->parameters->getData("followProject");
            if($followProject !== false && is_numeric($followProject)){
                $project = $this->entityManager->getRepository((Project::class))->find($followProject);
                if(!is_null($project)){
                    $this->parameters->putData('followProject', $project);
                } else Throw new NoFoundException();
            }

            //retrieve project for assigning project
            $assigningProject = $this->parameters->getData("assigningProject");
            if($assigningProject !== false && is_numeric($assigningProject)){
                $project = $this->entityManager->getRepository((Project::class))->find($assigningProject);
                if(!is_null($project)){
                    $this->parameters->putData('assigningProject', $project);
                } else Throw new NoFoundException();
            }

            //retrieve org for membership update
            $memberOf = $this->parameters->getData("memberOf");
            if($memberOf !== false && is_numeric($memberOf)){
                $org = $this->entityManager->getRepository(Organization::class)->find($memberOf);
                if(!is_null($org)){
                    $this->parameters->putData('memberOf', $org);
                }else Throw new NoFoundException();
            }

            $user = $this->userHandler->updateUser($this->getUser(), $user, $this->parameters->getAllData());

            $userData = [$this->userHandler->withPictures([$user])[0]];

            //make newToken with updatedUser and newInfos
            if($needNewToken){
                $userData['token'] = $JWTManager->create($user);
            }

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
     * @Route("/public/resetPassword", name="_password", methods="post")
     */
    public function resetPassword(Request $request): Response
    {
        try{
            // recover all data's request
            $this->parameters->setData($request);

            $this->parameters->hasData(["resetPasswordToken", "resetCode", "newPassword", "confirmPassword"]);

            //todo validator not really usse
            $this->validator->isInvalid([], ["password"], User::class);

            $this->userHandler->resetPassword($this->parameters->getAllData());

        return $this->responseHandler->successResponse();
        } catch(ViolationException | NoFoundException $e) {
            $this->logger->logError($e, $this->getUser(), "error");
            return $this->responseHandler->BadRequestResponse($e->getMessage());
        } catch (Exception $e) {//unexpected error
            $this->logger->logError($e, $this->getUser(), "error");
            return $this->responseHandler->serverErrorResponse("An error occurred");
        }
    }

    /*public function changeEmail(Request $request): Response
    {
        try{
            // recover all data's request
            $this->parameters->setData($request);


            //todo return user with newToken
            return $this->responseHandler->successResponse();
        } catch(ViolationException | NoFoundException $e) {
            $this->logger->logError($e, $this->getUser(), "error");
            return $this->responseHandler->BadRequestResponse($e->getMessage());
        } catch (Exception $e) {//unexpected error
            $this->logger->logError($e, $this->getUser(), "error");
            return $this->responseHandler->serverErrorResponse("An error occurred");
        }
    }*/
    /*
     * @Route("/email", name="_reset_email", methods="post")
     * @param Request $request
     * @param JWTTokenManagerInterface $JWTManager
     * @return Response
     */
  /*  public function changeEmail(Request  $request, JWTTokenManagerInterface $JWTManager): Response
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
    }*/

    /**
     * @return Response
     * @Route("/logout", name="_loggout", methods="delete")
     */
    public function logout() :Response {
        try{
            $tokenRefreshRepo = $this->entityManager->getRepository(JwtRefreshToken::class);
            $tokenRefresh = $tokenRefreshRepo->findOneBy(["username"=>$this->getUser()->getUsername()]);
            if(!is_null($tokenRefresh)){
                $this->entityManager->remove($tokenRefresh);
                $this->entityManager->flush();
            }
            return $this->responseHandler->successResponse([]);
        } catch(Exception $e){
            $this->logger->logError($e, $this->getUser(), "error");
            return $this->responseHandler->serverErrorResponse("An error occured");
        }
    }
}
