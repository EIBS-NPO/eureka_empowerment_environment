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
use App\Services\Entity\ActivityHandler;
use App\Services\Entity\OrgHandler;
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
    private OrgHandler $orgHandler;
    private ActivityHandler $activityHandler;

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
     * @param OrgHandler $orgHandler
     * @param ActivityHandler $activityHandler
     */
    public function __construct(RequestParameters $requestParameters, ResponseHandler $responseHandler, ParametersValidator $validator, EntityManagerInterface $entityManager, FileHandler $fileHandler, LogService $logger, FollowingHandler $followingHandler,
    ProjectHandler $projectHandler, OrgHandler $orgHandler, ActivityHandler $activityHandler)
    {
        $this->parameters = $requestParameters;
        $this->responseHandler = $responseHandler;
        $this->validator = $validator;
        $this->entityManager = $entityManager;
        $this->fileHandler = $fileHandler;
        $this->logger = $logger;
        $this->followingHandler = $followingHandler;

        $this->projectHandler = $projectHandler;
        $this->orgHandler = $orgHandler;
        $this->activityHandler = $activityHandler;
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
            $startDate = $this->parameters->getData('startDate');
            if($startDate !== false && $startDate !== "null"){
                $this->parameters->putData("startDate", new DateTime ($startDate));
            }
            $endDate = $this->parameters->getData('endDate');
            if($endDate !== false && $endDate !== "null"){
                $this->parameters->putData("endDate", new DateTime ($endDate));
            }

            //create
            $project = $this->projectHandler->create($this->getUser(), $this->parameters->getAllData());

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
     * @Route("/update", name="_put", methods="post")
     * @param Request $request
     * @return Response
     * @throws Exception
     */
    public function updateProject(Request $request) :Response
    {
        try{
        // recover all data's request
            $this->parameters->setData($request);
            $this->parameters->hasData(["id"]);

            //check if admin access required
            if($this->parameters->getData("admin")!== false){
                $this->denyAccessUnlessGranted('ROLE_ADMIN');
                //change accessTable for access by id
                $accessTable["access"] = "search";
            }

        //convert date
            $startDate = $this->parameters->getData('startDate');
            if($startDate !== false && $startDate !== "null" ){
                $this->parameters->putData("startDate", new DateTime ($startDate));
            }

            $endDate = $this->parameters->getData('endDate');
            if($endDate !== false && $endDate !== "null"){
                $this->parameters->putData("endDate", new DateTime ($endDate));
            }

        //retrieve project targeted
            $projectRepo = $this->entityManager->getRepository(Project::class);
            $project = $projectRepo->findOneBy(["id" => $this->parameters->getData("id")]);
            if(is_null($project))throw new NoFoundException("project id : ".$this->parameters->getData("id"));

            /*$project = $this->projectHandler->getProjects(
                $this->getUser(), [
                    "id" => $this->parameters->getData("id")
                ],
                true
            )[0];*/

        //handle potential link with an org
            $orgId = $this->parameters->getData("organization");
            if($orgId !== false){
                $org = null; //by default for delete linking
                if( is_numeric( $orgId ) ){
                    $orgRepo = $this->entityManager->getRepository(Organization::class);
                    $org = $orgRepo->findOneBy(["id" => $orgId]);
                 //   dd($this->entityManager->getMetadataFactory()->getMetadataFor(get_class($org))->getName());
                  //  $org = $this->orgHandler->getOrgs($this->getUser(), ["id" => $orgId], true)[0];
                }
                $this->parameters->putData("organization", $org);
            }

        //handle potential link with an activity
            $actId = $this->parameters->getData("activity");
            if($actId !== false){
                $activity = null;
                if( is_numeric( $actId ) ) {
                    $activityRepo = $this->entityManager->getRepository(Activity::class);
                    $activity = $activityRepo->findOneBy(["id" => $actId]);
                }
                $this->parameters->putData("activity", $activity);
            }

            //handle potential link with a member
            $memberId = $this->parameters->getData("member");
            if($memberId !== false){
                $member = null;
                if(is_numeric($memberId)){
                    $memberRepo = $this->entityManager->getRepository(User::class);
                    $member = $memberRepo->find($memberId);
                }
                $this->parameters->putData("member", $member);
            }

            $project = $this->projectHandler->update($this->getuser(), $project, $this->parameters->getAllData());

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

    /*
     * @param Request $request
     * @return Response
     * @Route("", name="_delete", methods="delete")
     */
    /*public function deleteProject(Request $request){
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
    }*/
}
