<?php

namespace App\Controller;

use App\Entity\FollowingProject;
use App\Entity\Project;
use App\Entity\User;
use App\Exceptions\SecurityException;
use App\Exceptions\ViolationException;
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

/**
 * Class FollowingProjectController
 * @package App\Controller
 * @Route("/followProject", name="follow_project")
 */
class FollowingProjectController extends AbstractController
{
    private RequestSecurity $security;
    private RequestParameters $parameters;
    private ResponseHandler $responseHandler;
    protected EntityManagerInterface $entityManager;
    private LogService $logger;

    /**
     * OrgController constructor.
     * @param RequestSecurity $requestSecurity
     * @param RequestParameters $requestParameters
     * @param ResponseHandler $responseHandler
     * @param EntityManagerInterface $entityManager
     * @param LogService $logger
     */
    public function __construct(RequestSecurity $requestSecurity, RequestParameters $requestParameters, ResponseHandler $responseHandler, EntityManagerInterface $entityManager, LogService $logger)
    {
        $this->security = $requestSecurity;
        $this->parameters = $requestParameters;
        $this->responseHandler = $responseHandler;
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
        try{$this->security->cleanXSS($request);}
        catch(SecurityException $e) {
            $this->logger->logError($e, $this->getUser(), "warning");
            return $this->responseHandler->forbidden();
        }

        // recover all data's request
        $this->parameters->setData($request);

        //check required params
        try{ $this->parameters->hasData(["projectId"]); }
        catch(ViolationException $e) {
            $this->logger->logError($e, $this->getUser(), "error");
            return $this->responseHandler->BadRequestResponse($e->getViolationsList());
        }
        //for following
        if($this->parameters->getData('isAssigning') === false){
            try{ $this->parameters->hasData(["isFollowing"]); }
            catch(ViolationException $e) {
                $this->logger->logError($e, $this->getUser(), "error");
                return $this->responseHandler->BadRequestResponse($e->getViolationsList());
            }
        }
        //for assigning
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
            //need current user id as creator
            $criterias["creator"] = $this->getUser()->getId();
        }

        //get project just by id for following or with creator for assigning
        $repository = $this->entityManager->getRepository(Project::class);
        $projectData = $repository->findBy($criterias);

        if(count($projectData) === 0 ){
            $this->logger->logInfo(" Project with id : ". $this->parameters->getData("projectId") ." not found " );
            return $this->responseHandler->BadRequestResponse(["project"=>"no_project_found"]);
        }

        $projectData = $projectData[0];


        //check if it's an assign query by project creator
        if($this->parameters->getData('isAssigning') !== false )
        {
            if(!$this->addAssigned($projectData)) return $this->responseHandler->BadRequestResponse(["email"=>"no_mail_account_found"]); //error response
        }
        else {// add simple following tag
            $this->addFollower($projectData);
        }

        //recup following after
        $following = $this->responseHandler->getDataResponse()[0];

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
            return $this->responseHandler->successResponse( $projectData->getAssignedTeam());
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
        try{$this->security->cleanXSS($request);}
        catch(SecurityException $e) {
            $this->logger->logError($e, $this->getUser(), "warning");
            return $this->responseHandler->forbidden();
        }

        // recover all data's request
        $this->parameters->setData($request);

        //check required params
        try{ $this->parameters->hasData(["projectId"]); }
        catch(ViolationException $e) {
            $this->logger->logError($e, $this->getUser(), "error");
            return $this->responseHandler->BadRequestResponse($e->getViolationsList());
        }
//for following
        if($this->parameters->getData('isAssigning') === false){
            try{ $this->parameters->hasData(["isFollowing"]); }
            catch(ViolationException $e) {
                $this->logger->logError($e, $this->getUser(), "error");
                return $this->responseHandler->BadRequestResponse($e->getViolationsList());
            }
        }
        //for assigning
        if($this->parameters->getData('isFollowing') === false ){
            try{ $this->parameters->hasData(["isAssigning", "userId"]); }
            catch(ViolationException $e) {
                $this->logger->logError($e, $this->getUser(), "error");
                return $this->responseHandler->BadRequestResponse($e->getViolationsList());
            }
        }

        //need id variable for column id in database
        $criterias["id"] = $this->parameters->getData('projectId');

        if($this->parameters->getData('isAssigning') !== false ){
            //need current user id as creator
            $criterias["creator"] = $this->getUser()->getId();
        }

        //get project just by id for following or with creator for assigning
        $repository = $this->entityManager->getRepository(Project::class);
        $projectData = $repository->findBy($criterias);

        if(count($projectData) === 0 ){
            $this->logger->logInfo(" Project with id : ". $this->parameters->getData("projectId") ." not found " );
            return $this->responseHandler->BadRequestResponse(["project"=>"no_project_found"]);
        }
        $projectData = $projectData[0];

        //check if it's an assign query by project creator
        if($this->parameters->getData('isAssigning') !== false)
        {
            if(!$this->rmvAssigned($projectData)) return $this->responseHandler->BadRequestResponse(["user"=>"no_found_in_assigned"]); // error response
        }
        else {// add simple following tag
            if(!$this->rmvFollower($projectData)) return $this->responseHandler->BadRequestResponse(["user"=>"no_found_in_followers"]); // error response
        }
        $following = $this->responseHandler->getDataResponse()[0];

        try{
            //check if the followingObject have again a following has true.
            if(!$following->isStillValid()){
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
            return $this->responseHandler->successResponse( $projectData->getAssignedTeam());
        }else {
            //response for following action
            return $this->responseHandler->successResponse( ["success"]);
        }

    }

    /**
     * API endPoint: return the following status (true/false) for current user into a project
     * nedd $projectId the project id target
     * @param Request $request
     * @return Response
     * @Route("", name="_get", methods="get")
     */
    public function getFollowingStatus(Request $request) :Response{
        try{$this->security->cleanXSS($request);}
        catch(SecurityException $e) {
            $this->logger->logError($e, $this->getUser(), "warning");
            return $this->responseHandler->forbidden();
        }

        // recover all data's request
        $this->parameters->setData($request);

        //check required params
        try{ $this->parameters->hasData(["projectId"]); }
        catch(ViolationException $e) {
            $this->logger->logError($e, $this->getUser(), "error");
            return $this->responseHandler->BadRequestResponse($e->getViolationsList());
        }
        //for following
        if($this->parameters->getData('isAssigning') === false){
            try{ $this->parameters->hasData(["isFollowing"]); }
            catch(ViolationException $e) {
                $this->logger->logError($e, $this->getUser(), "error");
                return $this->responseHandler->BadRequestResponse($e->getViolationsList());
            }
        }
        //for assigning
        if($this->parameters->getData('isFollowing') === false ){
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
        if($projectData->getCreator()->getId() !== $this->getUser()->getId()){
            $following = $projectData->getFollowingByUserId($this->getUser()->getId());

            if($following !== null){
                if($this->parameters->getData("isFollowing") !== false){
                    $response = $following->getIsFollowing();
                }
                else if($this->parameters->getData("isAssigning") !== false){
                    $response = $following->getIsAssigning();
                }
            }
        }
        else $response = true;

        return $this->responseHandler->successResponse([$response]);
    }

    /**
     * @param $project
     * @return bool
     */
    public function rmvAssigned($project) :bool {
        //get User by id
        $repository = $this->entityManager->getRepository(User::class);
        $user = $repository->findBy(["id" => $this->parameters->getData("userId")]);
        if(empty($user)) return false;
        $user = $user[0];

        //user have already a following?
        $following = $project->getFollowingByUserId($user->getId());
        if($following === null) return false;

        $following->setIsAssigning(false);

        $this->responseHandler->setDataResponse([$following]);
        return true;
    }

    /**
     * @param $project
     * @return bool
     */
    public function rmvFollower($project) :bool {
        //user have already a following?
        $following = $project->getFollowingByUserId($this->getUser()->getId());
        if($following === null) return false;

        $following->setIsFollowing(false);

        $this->responseHandler->setDataResponse([$following]);
        return true;
    }

    /**
     * add a follower into project
     * @param $project
     * @return bool
     */
    public function addFollower($project) :bool {

        //user have already a following?
        $following = $project->getFollowingByUserId($this->getUser()->getId());

        if($following === null){
            $following = new FollowingProject();
            $following->setIsAssigning(false);
            $following->setFollower($this->getUser());
            $following->setProject($project);
        }

        $following->setIsFollowing(true);
        $this->responseHandler->setDataResponse([$following]);
        return true;
    }

    /**
     * add an assigned user into project by his creator
     * @param $project
     * @return bool
     */
    public function addAssigned($project) :bool {
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
            $following = new FollowingProject();
            $following->setIsFollowing(false);
            $following->setFollower($user);
            $following->setProject($project);
        }
        $following->setIsAssigning(true);

        $this->responseHandler->setDataResponse([$following]);
        return true;
    }
}
