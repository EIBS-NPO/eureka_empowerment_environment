<?php

namespace App\Controller;

use App\Entity\Activity;
use App\Entity\Organization;
use App\Entity\Project;
use App\Entity\User;
use App\Exceptions\SecurityException;
use App\Exceptions\ViolationException;
use App\Service\FileHandler;
use App\Service\Security\FollowingHandler;
use App\Service\LogService;
use App\Service\Request\ParametersValidator;
use App\Service\Request\RequestParameters;
use App\Service\Request\ResponseHandler;
use App\Service\Security\RequestSecurity;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

//todo remove cleanXss
/**
 * Class ProjectController
 * @package App\Controller
 * @Route("/project", name="project")
 */
class ProjectController extends AbstractController
{

    private RequestSecurity $security;
    private RequestParameters $parameters;
    private ResponseHandler $responseHandler;
    private ParametersValidator $validator;
    protected EntityManagerInterface $entityManager;
    protected FileHandler $fileHandler;
    private LogService $logger;
    private FollowingHandler $followingHandler;

    /**
     * UserController constructor.
     * @param RequestSecurity $requestSecurity
     * @param RequestParameters $requestParameters
     * @param ResponseHandler $responseHandler
     * @param ParametersValidator $validator
     * @param EntityManagerInterface $entityManager
     * @param FileHandler $fileHandler
     * @param LogService $logger
     * @param FollowingHandler $followingHandler
     */
    public function __construct(RequestSecurity $requestSecurity, RequestParameters $requestParameters, ResponseHandler $responseHandler, ParametersValidator $validator, EntityManagerInterface $entityManager, FileHandler $fileHandler, LogService $logger, FollowingHandler $followingHandler)
    {
        $this->security = $requestSecurity;
        $this->parameters = $requestParameters;
        $this->responseHandler = $responseHandler;
        $this->validator = $validator;
        $this->entityManager = $entityManager;
        $this->fileHandler = $fileHandler;
        $this->logger = $logger;
        $this->followingHandler = $followingHandler;
    }

    /**
     * @Route("", name="_post", methods="post")
     * @param Request $request
     * @return Response
     * @throws Exception
     */
    public function create(Request $request): Response
    {
        try{$this->security->cleanXSS($request);}
        catch(SecurityException $e) {
            $this->logger->logError($e, $this->getUser(), "warning");
            return $this->responseHandler->forbidden();
        }

        // recover all data's request
        $this->parameters->setData($request);
        $this->parameters->addParam("creator", $this->getUser());

        if($this->parameters->getData('startDate') !== false){
            $this->parameters->putData("startDate", new DateTime ($this->parameters->getData('startDate')));
        }
        if($this->parameters->getData('endDate') !== false){
            $this->parameters->putData("endDate", new DateTime ($this->parameters->getData('endDate')));
        }

        //optional link with an organization : validation orgId & convert to Organization object
        if ($this->parameters->getData('orgId') !== false) {
            try{
                $repository = $this->entityManager->getRepository(User::class);
                $userData = $repository->findBy(["id" => $this->getUser()->getId()]);
                $user = $userData[0];

                $org = $user->getOrgById($this->parameters->getData('orgId'));
                if($org === false){
                    return $this->responseHandler->notFoundResponse();
                }else {
                    $this->parameters->addParam("organization", $org);
                }
            }catch(Exception $e){
                 $this->logger->logError($e,$this->getUser(),"error" );
                return $this->responseHandler->serverErrorResponse($e, "An error occured ");
            }
        }

        //check params Validations
        try{ $this->validator->isInvalid(
            ["creator", "title", "description"],
            ["startDate", "endDate", "organization"],
            Project::class);
        } catch(ViolationException $e){
            $this->logger->logError($e, $this->getUser(), "error");
            return $this->responseHandler->BadRequestResponse($e->getViolationsList());
        }

        //create project object && set validated fields
        $org = new Project();
        foreach( ["creator", "title", "description", "startDate", "endDate", "organization"]
                 as $field ) {
            if($this->parameters->getData($field) !== false ) {
                $setter = 'set'.ucfirst($field);
                $org->$setter($this->parameters->getData($field));
            }
        }

        //persist the new project
        try{
            $this->entityManager->persist($org);
            $this->entityManager->flush();
        }catch(Exception $e){
            $this->logger->logError($e,$this->getUser(),"error");
            return $this->responseHandler->serverErrorResponse($e, "An error occured");
        }

        //success
        return $this->responseHandler->successResponse([$org]);
    }





    /**
     * @Route("", name="_put", methods="put")
     * @param Request $request
     * @return Response
     * @throws Exception
     */
    public function updateProject (Request $request) :Response
    {
        try{$request = $this->security->cleanXSS($request);}
        catch(SecurityException $e) {
            $this->logger->logError($e, $this->getUser(), "warning");
            return $this->responseHandler->forbidden();
        }

        // recover all data's request
        $this->parameters->setData($request);
        if($this->parameters->getData('startDate') !== false){
            $this->parameters->putData("startDate", new DateTime ($this->parameters->getData('startDate')));
        }
        if($this->parameters->getData('endDate') !== false){
            $this->parameters->putData("endDate", new DateTime ($this->parameters->getData('endDate')));
        }

        //check if required params exist
        try{ $this->parameters->hasData(["projectId"]); }
        catch(ViolationException $e) {
            $this->logger->logError($e, $this->getUser(), "error");
            return $this->responseHandler->BadRequestResponse($e->getViolationsList());
        }

        //check params Validations
        try{ $this->validator->isInvalid(
            [],
            ["title", "description", "startDate", "endDate", "organization"],
            Project::class);
        } catch(ViolationException $e){
            $this->logger->logError($e, $this->getUser(), "error");
            return $this->responseHandler->BadRequestResponse($e->getViolationsList());
        }

        //for no admin get org by user
        if($this->getUser()->getRoles()[0] !== "ROLE_ADMIN"){
            $repository = $this->entityManager->getRepository(User::class);
            $userData = $repository->findBy(["id" => $this->getUser()->getId()]);
            $user = $userData[0];

            $projectData = $user->getProjectById($this->parameters->getData("projectId"));
        }
        else{//for admin
            $repository = $this->entityManager->getRepository(Project::class);
            $projectData = $repository->findBy(["id" => $this->parameters->getData("projectId")]);
            if(count($projectData) === 0 ){
                $this->logger->logInfo(" Project with id : ". $this->parameters->getData("projectId") ." not found " );
                return $this->responseHandler->notFoundResponse();
            }
            $projectData = $projectData[0];
        }

        //only for referent or admin
        if($projectData !== false ){
            foreach( ["title", "description", "startDate", "endDate", "organization"]
                     as $field ) {
                if($this->parameters->getData($field) !== false ) {
                    $setter = 'set'.ucfirst($field);
                    $projectData->$setter($this->parameters->getData($field));
                }
            }

            $this->entityManager->flush();
            //load picture
            foreach($projectData as $key => $project){
                foreach($project->getActivities() as $activity){
                    $activity = $this->fileHandler->loadPicture($activity);
                }
                if($project->getOrganization() !== null ){
                    $project->setOrganization( $this->fileHandler->loadPicture($project->getOrganization()));
                }
                $projects[$key] = $this->fileHandler->loadPicture($project);
                $projectData[$key] = $projects;
            }

        }else{
            $this->responseHandler->unauthorizedResponse("unauthorized");
        }

        //final response
        return $this->responseHandler->successResponse([$projectData], "read_project");
    }




    /**
     * @param Request $request
     * @return Response
     * @Route("/picture", name="_picture_put", methods="post")
     */
    public function putPicture(Request $request ) :Response {

        try{$request = $this->security->cleanXSS($request);}
        catch(SecurityException $e) {
            $this->logger->logError($e, $this->getUser(), "warning");
            return $this->responseHandler->forbidden();
        }

        // recover all data's request
        $this->parameters->setData($request);

        //check if required params exist
        try{ $this->parameters->hasData(["id", "image"]); }
        catch(ViolationException $e) {
            $this->logger->logError($e, $this->getUser(), "error");
            return $this->responseHandler->BadRequestResponse($e->getViolationsList());
        }

        try{
            //for no admin get org by user
            if($this->getUser()->getRoles()[0] !== "ROLE_ADMIN"){
                $repository = $this->entityManager->getRepository(User::class);
                $userData = $repository->findBy(["id" => $this->getUser()->getId()]);
                $user = $userData[0];

                $projectData = $user->getProjectById($this->parameters->getData("id"));
            }
            else{//for admin
                $repository = $this->entityManager->getRepository(Project::class);
                $projectData = $repository->findBy(["id" => $this->parameters->getData("id")]);
                if(count($projectData) === 0 ){
                    $this->logger->logInfo(" Project with id : ". $this->parameters->getData("id") ." not found " );
                    return $this->responseHandler->notFoundResponse();
                }
                $projectData = $projectData[0];
            }

            if($projectData !== false ){

                $oldPic = $projectData->getPicturePath() ? $projectData->getPicturePath() : null;

                $fileDir = '/pictures/Project';
                $picFile = $this->parameters->getData("image");

                //make unique picturePath
                $projectData->setPicturePath(uniqid().'_'. $this->fileHandler->getOriginalFilename($picFile).'.'. $picFile->guessExtension());

                //upload
                $this->fileHandler->upload($fileDir, $projectData->getPicturePath(), $picFile);

                $this->entityManager->flush();
                $projectData = $this->fileHandler->loadPicture($projectData);

                //if a picture already exist, need to remove it
                if($oldPic !== null){
                    $this->logger->logInfo(" User with id " . $this->getUser()->getId() . " remove old Picture for Project with id ". $projectData->getId() );
                    $this->fileHandler->removeFile($fileDir.'/'.$oldPic);
                }

            }else{
                $this->responseHandler->unauthorizedResponse("unauthorized");
            }

        }catch(Exception $e){
            $this->logger->logError($e,$this->getUser(),"error" );
            return $this->responseHandler->serverErrorResponse($e, "An error occured ");
        }

        //final response
        return $this->responseHandler->successResponse([$projectData], "read_project");
    }

//todo deletePicture


    /**
     * returns all projects in public context
     * @Route("/public", name="_get_public", methods="get")
     * @param Request $request
     * @return Response
     */
    public function getPublicProjects(Request $request): Response {
        try{$this->security->cleanXSS($request);}
        catch(SecurityException $e) {
            $this->logger->logError($e, $this->getUser(), "warning");
            return $this->responseHandler->forbidden();
        }

        // recover all data's request
        $this->parameters->setData($request);

        $criterias = [];
        if($this->parameters->getData('id') !== false){
            $criterias["id"]= $this->parameters->getData('id') ;
        }

        $repository = $this->entityManager->getRepository(Project::class);
        //get query, if id not define, query getALL
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

        //only with public resources
        foreach($dataResponse as $project) {
            $project->setActivities($project->getOnlyPublicActivities());
        }

        //download picture
        foreach($dataResponse as $key => $project){
            foreach($project->getActivities() as $activity){
                $activity = $this->fileHandler->loadPicture($activity);
            }
            if($project->getOrganization() !== null ){
                $project->setOrganization( $this->fileHandler->loadPicture($project->getOrganization()));
            }
            $dataResponse[$key] = $this->fileHandler->loadPicture($project);
        }

        //success response
        return $this->responseHandler->successResponse($dataResponse, "read_project");
    }

    /**
     * returns to a user his created projects
     * @Route("", name="_get", methods="get")
     * @param Request $request
     * @return Response
     */
    public function getProjects(Request $request): Response {
        try{$this->security->cleanXSS($request);}
        catch(SecurityException $e) {
            $this->logger->logError($e, $this->getUser(), "warning");
            return $this->responseHandler->forbidden();
        }

        // recover all data's request
        $this->parameters->setData($request);
        $criterias = [];
        if($this->parameters->getData("projectId") !== false){
            $criterias["id"]= $this->parameters->getData('projectId') ;
        }
        if( $this->parameters->getData("ctx") !== false){
            if($this->parameters->getData("ctx") === "creator"){
                $criterias["creator"] = $this->getUser()->getId();
            }
        }

        $repository = $this->entityManager->getRepository(Project::class);
        //get query, if id not define, query getALL
        try{
            if($this->getUser()->getRoles()[0] === "ROLE_ADMIN"){
                if($this->parameters->getData("projectId") !== false){
                    $dataResponse = $repository->findBy(["id" => $this->parameters->getData("projectId")]);
                }else{
                    $dataResponse = $repository->findAll();
                }
            }else{
                $dataResponse = $repository->findBy($criterias);
            }
        }catch(Exception $e){
            $this->logger->logError($e,$this->getUser(),"error" );
            return $this->responseHandler->serverErrorResponse($e, "An error occured");
        }

        $repository = $this->entityManager->getRepository(User::class);
        try {
            $userData = $repository->findBy(["id" => $this->getUser()->getId()]);
        }catch(Exception $e){
            $this->logger->logError($e,$this->getUser(),"error" );
            return $this->responseHandler->serverErrorResponse($e, "An error occured");
        }

        $user = $userData[0];

        //only if user isn't an admin.
        if($this->getUser()->getRoles()[0] !== "ROLE_ADMIN"){
            //check if public or private data return
            foreach($dataResponse as $project){
                if(!$this->followingHandler->isAssign($project, $this->getUser()))
                {
                    $project->setActivities($project->getOnlyPublicActivities());
                }
            }
        }

        //load picture
        foreach($dataResponse as $key => $project){
            foreach($project->getActivities() as $activity){
                $activity = $this->fileHandler->loadPicture($activity);
            }
            if($project->getOrganization() !== null ){
                $project->setOrganization( $this->fileHandler->loadPicture($project->getOrganization()));
            }
            $dataResponse[$key] = $this->fileHandler->loadPicture($project);
        }

        //success response
        return $this->responseHandler->successResponse($dataResponse, "read_project");
    }

    /**
     * @param Request $request
     * @return Response|null
     * @Route("/followed", name="_followed", methods="get")
     */
    public function getMyFollowing (Request $request) {
        try{$this->security->cleanXSS($request);}
        catch(SecurityException $e) {
            $this->logger->logError($e, $this->getUser(), "warning");
            return $this->responseHandler->forbidden();
        }

        // recover all data's request
        $this->parameters->setData($request);

        try{
            $repository = $this->entityManager->getRepository(User::class);
            $userData = $repository->findBy(["id" => $this->getUser()->getId()]);
            $user = $userData[0];

            $followingsProjects = $user->getFollowedProjects();

            foreach($followingsProjects as $key => $project){
                $followingsProjects[$key] = $this->fileHandler->loadPicture($project);
            }

            //filter for private resources
            foreach($followingsProjects as $project){
                if(!$project->isAssign($user)){
                    $project->setActivities($project->getOnlyPublicActivities());
                }
            }

            return $this->responseHandler->successResponse($followingsProjects,"read_project");

        }catch(Exception $e){
            $this->logger->logError($e,$this->getUser(),"error" );
            return $this->responseHandler->serverErrorResponse($e, "An error occured");
        }
    }

    /**
     * @param Request $request
     * @return Response
     * @Route("/assigned", name="_assigned", methods="get")
     */
    public function getAssignedProject(Request $request){
        try{$this->security->cleanXSS($request);}
        catch(SecurityException $e) {
            $this->logger->logError($e, $this->getUser(), "warning");
            return $this->responseHandler->forbidden();
        }

        // recover all data's request
        $this->parameters->setData($request);

        try{
            $repository = $this->entityManager->getRepository(User::class);
            $userData = $repository->findBy(["id" => $this->getUser()->getId()]);
            $user = $userData[0];

            $assignedProjects = $user->getAssignedProjects();
            foreach($assignedProjects as $key => $project){
                $assignedProjects[$key] = $this->fileHandler->loadPicture($project);
            }

            return $this->responseHandler->successResponse($assignedProjects, "read_project");

        }catch(Exception $e){
            $this->logger->logError($e,$this->getUser(),"error" );
            return $this->responseHandler->serverErrorResponse($e, "An error occured");
        }
    }

    /**
     * API andPoint: return the assigned user list for a project
     * need $projectId, the project id target
     * @param Request $request
     * @return Response|null
     * @Route("/team/public", name="_team", methods="get")
     */
    public function getTeam(Request $request) :Response {
        try{$this->security->cleanXSS($request);}
        catch(SecurityException $e) {
            $this->logger->logError($e, $this->getUser(), "warning");
            return $this->responseHandler->forbidden();
        }

        // recover all data's request
        $this->parameters->setData($request);

        //check if required params exist
        try{ $this->parameters->hasData(["projectId"]); }
        catch(ViolationException $e) {
            $this->logger->logError($e, $this->getUser(), "error");
            return $this->responseHandler->BadRequestResponse($e->getViolationsList());
        }

        try {
            $repository = $this->entityManager->getRepository(Project::class);
            $projectData = $repository->findBy(["id" => $this->parameters->getData("projectId")]);

            if(count($projectData) === 0 ){
                $this->logger->logInfo(" Organization with id : ". $this->parameters->getData("orgId") ." not found " );
                return $this->responseHandler->notFoundResponse();
            }

            //get collection of assigned user
            $team = $this->followingHandler->getAssignedTeam($projectData[0]);
            //download picture
            foreach($team as $key => $member){
                $team[$key] = $this->fileHandler->loadPicture($member);
            }

            return $this->responseHandler->successResponse($team);

        }catch(Exception $e){
            $this->logger->logError($e,$this->getUser(),"error" );
            return $this->responseHandler->serverErrorResponse($e, "An error occured");
        }
    }

    /**
     * @param Request $request
     * @return Response
     * @Route("/manageActivity", name="_add_activity", methods="put")
     */
    public function manageActivity(Request $request) {
        try{$this->security->cleanXSS($request);}
        catch(SecurityException $e) {
            $this->logger->logError($e, $this->getUser(), "warning");
            return $this->responseHandler->forbidden();
        }

        // recover all data's request
        $this->parameters->setData($request);

        //check if required params exist
        try{ $this->parameters->hasData(["activityId", "projectId"]); }
        catch(ViolationException $e) {
            $this->logger->logError($e, $this->getUser(), "error");
            return $this->responseHandler->BadRequestResponse($e->getViolationsList());
        }

        try{
//get Activity
            $repository = $this->entityManager->getRepository(Activity::class);
            $actData = $repository->findBy(["id" => $this->parameters->getData("activityId")]);
            if(count($actData) === 0 ){
                $this->logger->logInfo(" Activity with id : ". $this->parameters->getData("activityId") ." not found " );
                return $this->responseHandler->notFoundResponse();
            }
            $actData = $actData[0];

            //get project
            $repository = $this->entityManager->getRepository(Project::class);
            $projectData = $repository->findBy(["id" => $this->parameters->getData("projectId")]);
            if(count($projectData) === 0 ){
                $this->logger->logInfo(" Project with id : ". $this->parameters->getData("projectId") ." not found " );
                return $this->responseHandler->notFoundResponse();
            }
            $projectData = $projectData[0];

            //check manage access manage only for project creator, org referent and admin
            if($this->getUser()->getRoles()[0] !== "ROLE_ADMIN"){
                if(//if no referrent and no member && if no creator of the project
                    $projectData->getCreator()->getId() !== $this->getUser()->getId()
                    && $actData->getCreator()->getId() !== $this->getUser()->getId()

                ){
                    return $this->responseHandler->unauthorizedResponse("unauthorized");
                }
            }

            //if activity have the organization, remove it else add
            if($actData->getProject() !== null && $actData->getProject()->getId() === $projectData->getId()){
                $actData->setProject(null);
            }
            else { //add
                $actData->setProject($projectData);
            }

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
     * @Route("/manageOrg", name="_manage_org", methods="put")
     */
    public function manageOrg(Request $request){
        try{$this->security->cleanXSS($request);}
        catch(SecurityException $e) {
            $this->logger->logError($e, $this->getUser(), "warning");
            return $this->responseHandler->forbidden();
        }

        // recover all data's request
        $this->parameters->setData($request);

        //check if required params exist
        try{ $this->parameters->hasData(["orgId", "projectId"]); }
        catch(ViolationException $e) {
            $this->logger->logError($e, $this->getUser(), "error");
            return $this->responseHandler->BadRequestResponse($e->getViolationsList());
        }

        try{
            //get Activity
            $repository = $this->entityManager->getRepository(Organization::class);
            $orgData = $repository->findBy(["id" => $this->parameters->getData("orgId")]);
            if(count($orgData) === 0 ){
                $this->logger->logInfo(" Organization with id : ". $this->parameters->getData("orgId") ." not found " );
                return $this->responseHandler->notFoundResponse();
            }
            $orgData = $orgData[0];

            //get project
            $repository = $this->entityManager->getRepository(Project::class);
            $projectData = $repository->findBy(["id" => $this->parameters->getData("projectId")]);
            if(count($projectData) === 0 ){
                $this->logger->logInfo(" Organization with id : ". $this->parameters->getData("projectId") ." not found " );
                return $this->responseHandler->notFoundResponse();
            }
            $projectData = $projectData[0];

            //check manage access manage only for project creator, org referent and admin
            if($this->getUser()->getRoles()[0] !== "ROLE_ADMIN"){
                if(//if no referrent and no member && if no creator of the project
                    $projectData->getCreator()->getId() !== $this->getUser()->getId()
                 //   !$projectData->isAssign($this->getUser())
                    && $orgData->getReferent()->getId() !== $this->getUser()->getId()

                ){
                    return $this->responseHandler->unauthorizedResponse("unauthorized");
                }
            }

            //if activity have the organization, remove it else add
            if($projectData->getOrganization() !== null && $projectData->getOrganization()->getId() === $orgData->getId()){
                $projectData->setOrganization(null);
            }
            else { //add
                $projectData->setOrganization($orgData);
            }

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
     * @Route("", name="_delete", methods="delete")
     */
    public function deleteProject(Request $request){
        try{$this->security->cleanXSS($request);}
        catch(SecurityException $e) {
            $this->logger->logError($e, $this->getUser(), "warning");
            return $this->responseHandler->forbidden();
        }

        // recover all data's request
        $this->parameters->setData($request);

        //check if required params exist
        try{ $this->parameters->hasData(["projectId"]); }
        catch(ViolationException $e) {
            $this->logger->logError($e, $this->getUser(), "error");
            return $this->responseHandler->BadRequestResponse($e->getViolationsList());
        }

        try {
            //for no admin get org by user
            if ($this->getUser()->getRoles()[0] !== "ROLE_ADMIN") {
                $repository = $this->entityManager->getRepository(User::class);
                $userData = $repository->findBy(["id" => $this->getUser()->getId()]);
                $user = $userData[0];

                $projectData = $user->getProjectById($this->parameters->getData("projectId"));
            } else {//for admin
                $repository = $this->entityManager->getRepository(Project::class);
                $projectData = $repository->findBy(["id" => $this->parameters->getData("projectId")]);
                if (count($projectData) === 0) {
                    $this->logger->logInfo(" Project with id : " . $this->parameters->getData("projectId") . " not found ");
                    return $this->responseHandler->notFoundResponse();
                }
                $projectData = $projectData[0];
            }

            $this->entityManager->remove($projectData);
            $this->entityManager->flush();

            return $this->responseHandler->successResponse(["success"]);

        }catch(Exception $e){
            $this->logger->logError($e,$this->getUser(),"error" );
            return $this->responseHandler->serverErrorResponse($e, "An error occured");
        }
    }
}
