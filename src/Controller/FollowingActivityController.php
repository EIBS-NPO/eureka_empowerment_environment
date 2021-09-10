<?php

namespace App\Controller;

use App\Entity\Activity;
use App\Entity\User;
use App\Exceptions\SecurityException;
use App\Exceptions\ViolationException;
use App\Services\FileHandler;
use App\Services\LogService;
use App\Services\Request\RequestParameters;
use App\Services\Request\ResponseHandler;
use App\Services\Security\RequestSecurity;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class FollowingActivityController
 * @package App\Controller
 * @Route("/followActivity", name="follow_activity")
 */
class FollowingActivityController extends AbstractController
{
    private RequestParameters $parameters;
    private ResponseHandler $responseHandler;
    protected EntityManagerInterface $entityManager;
    private  FileHandler $fileHandler;
    private LogService $logger;

    /**
     * OrgController constructor.
     * @param RequestParameters $requestParameters
     * @param ResponseHandler $responseHandler
     * @param EntityManagerInterface $entityManager
     * @param FileHandler $fileHandler
     * @param LogService $logger
     */
    public function __construct(RequestParameters $requestParameters, ResponseHandler $responseHandler, EntityManagerInterface $entityManager, FileHandler $fileHandler, LogService $logger)
    {
        $this->parameters = $requestParameters;
        $this->responseHandler = $responseHandler;
        $this->entityManager = $entityManager;
        $this->fileHandler = $fileHandler;
        $this->logger = $logger;
    }

    /**
     * @param Request $request
     * @return Response
     * @Route("/add", name="_add", methods="put")
     */
    public function addFollower(Request $request) :Response {
        // recover all data's request
        $this->parameters->setData($request);

        //check required params
        try{ $this->parameters->hasData(["activityId"]); }
        catch(ViolationException $e) {
            $this->logger->logError($e, $this->getUser(), "error");
            return $this->responseHandler->BadRequestResponse($e->getViolationsList());
        }

        try{
            //get Activity target for link
            $repository = $this->entityManager->getRepository(Activity::class);
            $activityData = $repository->findBy(["id" => $this->parameters->getData("activityId")]);

            if(count($activityData) === 0 ){
                $this->logger->logInfo(" Actvity with id : ". $this->parameters->getData("activityId") ." not found " );
                return $this->responseHandler->BadRequestResponse(["activity"=>"no_activity_found"]);
            }
            $activity = $activityData[0];

            //getUser
            $repository = $this->entityManager->getRepository(User::class);
            $user = $repository->findBy(["id" => $this->getUser()->getId()]);

            $user = $user[0];

            $user->addFollowingActivity($activity);

            //if($this->updateEntity($user)) return $this->response;
            $this->entityManager->flush();
        }catch(Exception $e){
            $this->logger->logError($e,$this->getUser(),"error" );
            return $this->responseHandler->serverErrorResponse($e, "An error occured");
        }

        return $this->responseHandler->successResponse(["success"]);
    }

    /**
     * @param Request $request
     * @return Response|null
     * @Route("/remove", name="_remove", methods="put")
     */
    public function removeFollower(Request $request){
        // recover all data's request
        $this->parameters->setData($request);

        //check required params
        try{ $this->parameters->hasData(["activityId"]); }
        catch(ViolationException $e) {
            $this->logger->logError($e, $this->getUser(), "error");
            return $this->responseHandler->BadRequestResponse($e->getViolationsList());
        }

        try{
            //get Activity target for link
            $repository = $this->entityManager->getRepository(Activity::class);
            $activityData = $repository->findBy(["id" => $this->parameters->getData("activityId")]);

            if(count($activityData) === 0 ){
                $this->logger->logInfo(" Actvity with id : ". $this->parameters->getData("activityId") ." not found " );
                return $this->responseHandler->BadRequestResponse(["activity"=>"no_activity_found"]);
            }
            $activity = $activityData[0];


            //getUser
            $repository = $this->entityManager->getRepository(User::class);
            $user = $repository->findBy(["id" => $this->getUser()->getId()]);

            $user = $user[0];

            $user->removeFollowingActivity($activity);

            $this->entityManager->flush();

            return $this->responseHandler->successResponse(["success"]);

        }catch(Exception $e){
            $this->logger->logError($e,$this->getUser(),"error" );
            return $this->responseHandler->serverErrorResponse($e, "An error occured");
        }
    }

    /**
     * @param Request $request
     * @return Response
     * @Route("/public", name="_get", methods="get")
     */
    public function getFellowers(Request $request) : Response {
        // recover all data's request
        $this->parameters->setData($request);

        //check required params
        try{ $this->parameters->hasData(["id"]); }
        catch(ViolationException $e) {
            $this->logger->logError($e, $this->getUser(), "error");
            return $this->responseHandler->BadRequestResponse($e->getViolationsList());
        }

        //
        try{
            $repository = $this->entityManager->getRepository(Activity::class);
            $activityData = $repository->findBy(["id" => $this->parameters->getData("activityId")]);

            if(empty($activityData)){
                $this->logger->logInfo(" Actvity with id : ". $this->parameters->getData("activityId") ." not found " );
                return $this->responseHandler->BadRequestResponse(["activity"=>"no_activity_found"]);
            }
            else {
                $followers = $activityData[0]->getFollowers()->toArray();
                foreach($followers as $key => $follower){
                    $followers[$key] = $this->fileHandler->loadPicture($follower);
                }
            }

            return $this->responseHandler->successResponse([$followers]);

        }catch(Exception $e){
            $this->logger->logError($e,$this->getUser(),"error" );
            return $this->responseHandler->serverErrorResponse($e, "An error occured");
        }
    }

    /**
     * @param Request $request
     * @return Response|null
     * @Route("/myFavorites", name="_myFavorites", methods="get")
     */
    public function getMyFollowing (Request $request) {
        // recover all data's request
        $this->parameters->setData($request);

        try {
            $repository = $this->entityManager->getRepository(User::class);
            $user = $repository->findBy(["id" => $this->getUser()->getId()])[0];

            $followActivities = $user->getFollowingActivities()->toArray();
            foreach($followActivities as $key => $activity){
                $followActivities[$key] = $this->fileHandler->loadPicture($activity);
            }

            return $this->responseHandler->successResponse($followActivities);

        }catch(Exception $e){
            $this->logger->logError($e,$this->getUser(),"error" );
            return $this->responseHandler->serverErrorResponse($e, "An error occured");
        }
    }

    /**
     * API endPoint: return the following status for current user into a activity
     * nedd $projectId the activity id target
     * @param Request $request
     * @return Response
     * @Route("", name="_get", methods="get")
     */
    public function getFollowingStatus(Request $request) :Response{
        // recover all data's request
        $this->parameters->setData($request);

        //check required params
        try{ $this->parameters->hasData(["activityId"]); }
        catch(ViolationException $e) {
            $this->logger->logError($e, $this->getUser(), "error");
            return $this->responseHandler->BadRequestResponse($e->getViolationsList());
        }

        try{
            $repository = $this->entityManager->getRepository(Activity::class);
            $activityData = $repository->findBy(["id" => $this->parameters->getData("activityId")]);

            if(empty($activityData)){
                $this->logger->logInfo(" Actvity with id : ". $this->parameters->getData("activityId") ." not found " );
                return $this->responseHandler->BadRequestResponse(["activity"=>"no_activity_found"]);
            }
            $isFollow = $activityData[0]->isFollowByUserId($this->getUser()->getId());

            return $this->responseHandler->successResponse([$isFollow]);

        }catch(Exception $e){
            $this->logger->logError($e,$this->getUser(),"error" );
            return $this->responseHandler->serverErrorResponse($e, "An error occured");
        }
    }
}
