<?php

namespace App\Controller;

use App\Entity\Following;
use App\Entity\Project;
use App\Entity\User;
use App\Exceptions\SecurityException;
use App\Exceptions\ViolationException;
use App\Service\Security\FollowingHandler;
use App\Service\LogService;
use App\Service\Request\RequestParameters;
use App\Service\Request\ResponseHandler;
use App\Service\Security\RequestSecurity;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

//TODO remove cleanXss
/**
 * Class FollowingProjectController
 * @package App\Controller
 * @Route("/followProject", name="follow_project")
 */
class FollowingProjectController extends AbstractController
{
    private RequestParameters $parameters;
    private ResponseHandler $responseHandler;
    private FollowingHandler $followingHandler;
    protected EntityManagerInterface $entityManager;
    private LogService $logger;

    /**
     * OrgController constructor.
     * @param RequestParameters $requestParameters
     * @param ResponseHandler $responseHandler
     * @param FollowingHandler $followingHandler
     * @param EntityManagerInterface $entityManager
     * @param LogService $logger
     */
    public function __construct( RequestParameters $requestParameters, ResponseHandler $responseHandler, FollowingHandler $followingHandler, EntityManagerInterface $entityManager, LogService $logger)
    {
        $this->parameters = $requestParameters;
        $this->responseHandler = $responseHandler;
        $this->followingHandler = $followingHandler;
        $this->entityManager = $entityManager;
        $this->logger = $logger;
    }

    /**
     * API endPoint: create or update a Following entity in a project
     * need $projectId, the project id target
     * need $email, the user target email, for an Assigned action, not for isFollowing( it's the current user)
     * need $isAssigning boolean for assign a user OR $isFollowing boolean for follow a user
     * @param Request $request
     * @return Response
     * @Route("", name="_add", methods="post")
     */
    public function add(Request $request) :Response {
        // recover all data's request
        $this->parameters->setData($request);
        //check required params
        try{ $this->parameters->hasData(["projectId"]); }
        catch(ViolationException $e) {
            $this->logger->logError($e, $this->getUser(), "error");
            return $this->responseHandler->BadRequestResponse($e->getViolationsList());
        }

        //for add following
        if($this->parameters->getData('isAssigning') === false){
            try{ $this->parameters->hasData(["isFollowing"]); }
            catch(ViolationException $e) {
                $this->logger->logError($e, $this->getUser(), "error");
                return $this->responseHandler->BadRequestResponse($e->getViolationsList());
            }
        }
        //for add assigning
        if($this->parameters->getData('isFollowing') === false ){
            try{ $this->parameters->hasData(["isAssigning", "email"]); }
            catch(ViolationException $e) {
                $this->logger->logError($e, $this->getUser(), "error");
                return $this->responseHandler->BadRequestResponse($e->getViolationsList());
            }
        }

        //need id variable for column id in database
        $criterias["id"] = $this->parameters->getData('projectId');

        if($this->parameters->getData('isAssigning') !== false ){
            //todo check authorization only for creator or admin
            //need current user id as creator
            $criterias["creator"] = $this->getUser()->getId();
        }

        //get project just by id for following or with creator for assigning
        $repository = $this->entityManager->getRepository(Project::class);
        $projectData = $repository->findOneBy($criterias);

        if($projectData === null || get_class($projectData) !== Project::class ){
            $this->logger->logInfo(" Project with id : ". $this->parameters->getData("projectId") ." not found " );
            return $this->responseHandler->BadRequestResponse(["project"=>"no_project_found"]);
        }

        //todo : doctrine probably not return a Project object but just an Object... see by repo for typage?
       /* if(get_class($projectData[0]) === Project::class){
            $projectData = $projectData[0];
        }*/


        //check if it's an assign query by project creator
        if($this->parameters->getData('isAssigning') !== false )
        {
            //get User by email
            $repository = $this->entityManager->getRepository(User::class);
            $user = $repository->findOneBy(["email" => $this->parameters->getData("email")]);
            if(get_class($user) !== User::class ){
                $this->logger->logInfo(" User with email : ". $this->parameters->getData("email") ." not found " );
                return $this->responseHandler->BadRequestResponse(["email"=>"no_mail_account_found"]); //error response
            }

            $following = $this->followingHandler->addAssigned($projectData, $user);
        }
        else {// else add simple following tag
            $following = $this->followingHandler->addFollower($projectData, $this->getUser());
        }

        //recup following after
    //    $following = $this->responseHandler->getDataResponse()[0];

        try{
            if($following->getId() === null){ //if it's a new following
                $this->entityManager->persist($following);
            }
            $this->entityManager->flush();
        }catch(Exception $e){
            $this->logger->logError($e,$this->getUser(),"error" );
            return $this->responseHandler->serverErrorResponse($e, "An error occured");
        }

        $projectData->addFollowing($following);

        //make response
        if($this->parameters->getData('isAssigning') !== false ){
            //response for assigning action
            return $this->responseHandler->successResponse( $this->followingHandler->getAssignedTeam($projectData));
        }else {
            //response for following action
            return $this->responseHandler->successResponse( ["success"]);
        }
    }



    /**
     * API endPoint: update or remove a Following entity in a project
     * need $projectId, the project id target
     * need $userId, the user target id, for an Assigned action, not for isFollowing( it's the current user)
     * need $isAssigning boolean for unAssign a user OR $isFollowing boolean for unFollow a user
     * @param Request $request
     * @return Response|null
     * @Route("", name="_remove", methods="put")
     */
    public function remove(Request $request){
        // recover all data's request
        $this->parameters->setData($request);

        //check required params
        try{ $this->parameters->hasData(["projectId"]); }
        catch(ViolationException $e) {
            $this->logger->logError($e, $this->getUser(), "error");
            return $this->responseHandler->BadRequestResponse($e->getViolationsList());
        }
//for following
        if(!isset($this->parameters->getAllData()['isAssigning'])){
            try{ $this->parameters->hasData(["isFollowing"]); }
            catch(ViolationException $e) {
                $this->logger->logError($e, $this->getUser(), "error");
                return $this->responseHandler->BadRequestResponse($e->getViolationsList());
            }
        }
        //for assigning
        if(!isset($this->parameters->getAllData()['isFollowing'])){
            try{ $this->parameters->hasData(["isAssigning", "userId"]); }
            catch(ViolationException $e) {
                $this->logger->logError($e, $this->getUser(), "error");
                return $this->responseHandler->BadRequestResponse($e->getViolationsList());
            }
        }

        //make criterias forthe query
        //add the projectId fo id column
        $criterias["id"] = $this->parameters->getData('projectId');
        //if it's a assigning request, add creator for query the project.
        if(isset($this->parameters->getAllData()['isAssigning'])){
            //need current user id as creator
            $criterias["creator"] = $this->getUser()->getId();
        }
        //get project just by id for following or with creator for assigning
        $repository = $this->entityManager->getRepository(Project::class);
        $projectData = $repository->findBy($criterias);

        //if no resutl
        if(count($projectData) === 0 ){
            $this->logger->logInfo(" Project with id : ". $this->parameters->getData("projectId") ." not found " );
            return $this->responseHandler->BadRequestResponse(["project"=>"no_project_found"]);
        }

        //extraction of the project from the result table
        $projectData = $projectData[0];

        //service call according to the action requested (assigning or following)
        if(isset($this->parameters->getAllData()['isAssigning']))
        {
            if(!$this->rmvAssigned($projectData)) return $this->responseHandler->BadRequestResponse(["user"=>"no_found_in_assigned"]); // error response
        }
        else {// add simple following tag
            if(!$this->rmvFollower($projectData)) return $this->responseHandler->BadRequestResponse(["user"=>"no_found_in_followers"]); // error response
        }
        $following = $this->responseHandler->getDataResponse()[0];

        try{
            //check if the followingObject have again a following has true.
            if(!$this->followingHandler->isStillValid($following)){
                $this->entityManager->remove($following);
            }
            $this->entityManager->flush();
        }catch(Exception $e){
            $this->logger->logError($e,$this->getUser(),"error" );
            return $this->responseHandler->serverErrorResponse($e, "An error occured");
        }

        $projectData->addFollowing($following);

        //make response
        if($this->parameters->getData('isAssigning') !== false ){
            //response for assigning action
            return $this->responseHandler->successResponse( $this->followingHandler->getAssignedTeam($projectData));
        }else {
            //response for following action
            return $this->responseHandler->successResponse( ["success"]);
        }

    }

    /**
     * API endPoint: return the following status (true/false) for current user into a project
     * need $projectId: the project id target and isfollowing or isassigning with true value
     * @param Request $request
     * @return Response
     * @Route("", name="_get", methods="get")
     */
    public function getFollowingStatus(Request $request) :Response{
        // recover all data's request
        $this->parameters->setData($request);

        //check required params
        try{ $this->parameters->hasData(["projectId"]); }
        catch(ViolationException $e) {
            $this->logger->logError($e, $this->getUser(), "error");
            return $this->responseHandler->BadRequestResponse($e->getViolationsList());
        }
        //for following
        if(!isset($this->parameters->getAllData()['isAssigning'])){
            try{ $this->parameters->hasData(["isFollowing"]); }
            catch(ViolationException $e) {
                $this->logger->logError($e, $this->getUser(), "error");
                return $this->responseHandler->BadRequestResponse($e->getViolationsList());
            }
        }
        //for assigning
        if(!isset($this->parameters->getAllData()['isFollowing'])){
            try{ $this->parameters->hasData(["isAssigning"]); }
            catch(ViolationException $e) {
                $this->logger->logError($e, $this->getUser(), "error");
                return $this->responseHandler->BadRequestResponse($e->getViolationsList());
            }
        }

        //get project just by id for following or with creator for assigning
        $repository = $this->entityManager->getRepository(Project::class);
        $projectData = $repository->findBy(["id" => $this->parameters->getData("projectId")]);

        if(count($projectData) === 0 ){
            $this->logger->logInfo(" Project with id : ". $this->parameters->getData("projectId") ." not found " );
            return $this->responseHandler->BadRequestResponse(["project"=>"no_project_found"]);
        }
        $projectData = $projectData[0];

        $response = false;
        if(isset($this->parameters->getAllData()["isAssigning"])){
            //$response = $projectData->isAssign($this->getUser());
            $response = $this->followingHandler->isAssign($projectData,$this->getUser());
        }else if(isset($this->parameters->getAllData()["isFollowing"])){
            $response = $this->followingHandler->isFollowed($projectData, $this->getUser());
            /*$following = $projectData->getFollowingByUserId($this->getUser()->getId());
            if($following !== null){
                if(isset($this->parameters->getAllData()["isFollowing"])){
                    $response = $following->getIsFollowing();
                }
            }*/
        }

        return $this->responseHandler->successResponse([$response]);
    }

    /**
     * @param $project
     * @return bool
     */
    public function rmvAssigned($project) :bool {
        //get User by id
       /* $repository = $this->entityManager->getRepository(User::class);
        $user = $repository->findBy(["id" => $this->parameters->getData("userId")]);
        if(empty($user)) return false;
        $user = $user[0];*/

        //user have already a following?
        $following = $this->followingHandler->getFollowingByFollowerId($project, $this->getUser()->getId());
        if($following === null) return false;

        $this->followingHandler->rmvAssigned($following);
        $this->responseHandler->setDataResponse([$following]);
        return true;
    }

    /**
     * @param $project
     * @return bool
     */
    public function rmvFollower($project) :bool {
        //user have already a following?
        $following = $this->followingHandler->getFollowingByFollowerId($project, $this->getUser()->getId());
        if($following === null) return false;

        $this->followingHandler->rmvFollower($following);
        $this->responseHandler->setDataResponse([$following]);
        return true;
    }

    /**
     * add a follower into project
     * @param $project
     * @return bool
     */
    /*private function addFollower($project) :bool {

        //user have already a following?
        $following = $project->getFollowingByUserId($this->getUser()->getId());

        if($following === null){
            $following = new Following();
            $following->setIsAssigning(false);
            $following->setFollower($this->getUser());
            $following->setObject($project);
        }

        $following->setIsFollowing(true);
        $this->responseHandler->setDataResponse([$following]);
        return true;
    }*/

    //todo check for mailService
    /**
     * add an assigned user into project by his creator
     * @param $project
     * @return bool
     */
    /*private function addAssigned($project) :bool {
        //get User by email
        $repository = $this->entityManager->getRepository(User::class);
        $user = $repository->findBy(["email" => $this->parameters->getData("email")]);
        if(empty($user)) return false;

        if(count($user) === 0 ){
            $this->logger->logInfo(" User with email : ". $this->parameters->getData("email") ." not found " );
            return false;
        }
        $user = $user[0];

        //user have already a following?
        $following = $project->getFollowingByUserId($user->getId());
        if($following === null) { //if need a new following object
            $following = new Following();
            $following->setIsFollowing(false);
            $following->setFollower($user);
            $following->setObject($project);
        }
        $following->setIsAssigning(true);

        $this->responseHandler->setDataResponse([$following]);
        return true;
    }*/
}
