<?php

namespace App\Controller;

use App\Entity\Activity;
use App\Entity\Organization;
use App\Entity\Project;
use App\Entity\User;
use App\Exceptions\BadMediaFileException;
use App\Exceptions\NoFoundException;
use App\Exceptions\PartialContentException;
use App\Exceptions\ViolationException;
use App\Services\Entity\ProjectHandler;
use App\Services\FileHandler;
use App\Services\Entity\FollowingHandler;
use App\Services\LogService;
use App\Services\Request\ParametersValidator;
use App\Services\Request\RequestParameters;
use App\Services\Request\ResponseHandler;
use App\Services\Security\RequestSecurity;
use DateTime;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class ProjectController
 * @package App\Controller
 * @Route("/project", name="project")
 */
class ProjectController extends AbstractController
{

    private RequestParameters $parameters;
    private ResponseHandler $responseHandler;
    private ParametersValidator $validator;
    protected EntityManagerInterface $entityManager;
    protected FileHandler $fileHandler;
    private LogService $logger;
    private FollowingHandler $followingHandler;

    private ProjectHandler $projectHandler;

    /**
     * UserController constructor.
     * @param RequestParameters $requestParameters
     * @param ResponseHandler $responseHandler
     * @param ParametersValidator $validator
     * @param EntityManagerInterface $entityManager
     * @param FileHandler $fileHandler
     * @param LogService $logger
     * @param FollowingHandler $followingHandler
     * @param ProjectHandler $projectHandler
     */
    public function __construct(RequestParameters $requestParameters, ResponseHandler $responseHandler, ParametersValidator $validator, EntityManagerInterface $entityManager, FileHandler $fileHandler, LogService $logger, FollowingHandler $followingHandler,
    ProjectHandler $projectHandler)
    {
        $this->parameters = $requestParameters;
        $this->responseHandler = $responseHandler;
        $this->validator = $validator;
        $this->entityManager = $entityManager;
        $this->fileHandler = $fileHandler;
        $this->logger = $logger;
        $this->followingHandler = $followingHandler;

        $this->projectHandler = $projectHandler;
    }

    /**
     * @Route("", name="_post", methods="post")
     * @param Request $request
     * @return Response
     * @throws Exception
     */
    public function create(Request $request): Response
    {
        try{
            // recover all data's request
            $this->parameters->setData($request);
            $this->parameters->addParam("creator", $this->getUser());

            //convert Date
            if($this->parameters->getData('startDate') !== false){
                $this->parameters->putData("startDate", new DateTime ($this->parameters->getData('startDate')));
            }
            if($this->parameters->getData('endDate') !== false){
                $this->parameters->putData("endDate", new DateTime ($this->parameters->getData('endDate')));
            }

            //create
            $project = $this->projectHandler->create($this->parameters->getAllData());

            //loadPicture
            $project = $this->projectHandler->withPictures([$project]);
        //success
        return $this->responseHandler->successResponse($project);
        }
        catch(PartialContentException $e){
            $this->logger->logError($e, $this->getUser(), "error");
            return $this->responseHandler->partialResponse($e, "read_project");
        }
        catch(ViolationException $e) {
            $this->logger->logError($e, $this->getUser(), "error");
            return $this->responseHandler->BadRequestResponse($e->getMessage());
        }
        catch(Exception $e){
            $this->logger->logError($e,$this->getUser(),"error");
            return $this->responseHandler->serverErrorResponse( "An error occured");
        }
    }


    /**
     * returns all projects in public context
     * @Route("/public", name="_get_public", methods="get")
     * @param Request $request
     * @return Response
     */
    public function getPublic(Request $request): Response {
        try{
            // recover all data's request
            $this->parameters->setData($request);

            $projects = $this->projectHandler->getProjects(null,$this->parameters->getAllData());

            $projects = $this->projectHandler->withPictures($projects);
            //success response
            return $this->responseHandler->successResponse($projects, "read_project");
        }catch(Exception $e){
            $this->logger->logError($e,$this->getUser(),"error" );
            return $this->responseHandler->serverErrorResponse("An error occured");
        }
    }


    /**
     * return project for connected user with handle of owned, assigned or followed project
     * @Route("", name="_get", methods="get")
     * @param Request $request
     * @return Response
     */
    public function getPrivate(Request $request): Response
    {
        try {
            // recover all data's request
            $this->parameters->setData($request);

            $projects = $this->projectHandler->getProjects($this->getUser(), $this->parameters->getAllData());

            $projects = $this->projectHandler->withPictures($projects);

            return $this->responseHandler->successResponse($projects, "read_project");
        }
        catch (Exception $e) {
            $this->logger->logError($e, $this->getUser(), "error");
            return $this->responseHandler->serverErrorResponse("An error occured");
        }
    }


    /**
     * @Route("", name="_put", methods="put")
     * @param Request $request
     * @return Response
     * @throws Exception
     */
    public function updateProject (Request $request) :Response
    {
        try{
            // recover all data's request
            $this->parameters->setData($request);
            $this->parameters->hasData(["id"]);

            if($this->parameters->getData('startDate') !== false){
                $this->parameters->putData("startDate", new DateTime ($this->parameters->getData('startDate')));
            }
            if($this->parameters->getData('endDate') !== false){
                $this->parameters->putData("endDate", new DateTime ($this->parameters->getData('endDate')));
            }

            $project = $this->projectHandler->getProjects(
                $this->getUser(), [
                "id" => $this->parameters->getData("id"),
                "access" => "owned"],
                true
            )[0];

            $project = $this->projectHandler->update($project, $this->parameters->getAllData());

            $project = $this->projectHandler->withPictures([$project]);

            //final response
            return $this->responseHandler->successResponse($project, "read_project");
        }
        catch(ViolationException | NoFoundException $e) {
            $this->logger->logError($e, $this->getUser(), "error");
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
            $this->parameters->hasData(["id", "pictureFile"]);

            //get org by id with owned context and notFoundException
            $project = $this->projectHandler->getProjects(
                $this->getUser(), [
                "id" => $this->parameters->getData("id"),
                "access" => "owned"],
                true
            )[0];

            $project = $this->projectHandler->putPicture($project,$this->parameters->getAllData());

            $project = $this->projectHandler->withPictures([$project]);

            return $this->responseHandler->successResponse($project, "read_org");
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
     * return the assigned user list for a project
     * need $projectId, the project id target
     * @param Request $request
     * @return Response
     * @Route("/team/public", name="_team", methods="get")
     */
    public function getTeam(Request $request) :Response {
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

            $team = $this->projectHandler->getTeam($projectData[0]);
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

//retirer le referent de l'org de l'assignation du projet
                //non laisser faire manuellement
                /*$referentProjectFollowing = $this->followingHandler->getFollowingByFollowerId($projectData, $orgData->getReferent());
                $this->followingHandler->rmvAssigned($referentProjectFollowing);*/
            }
            else { //add
                $projectData->setOrganization($orgData);

                //add orgReferent into assignedTeam of the project
                $this->followingHandler->addAssigned($projectData, $orgData->getReferent());

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
