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
use App\Services\Entity\UserHandler;
use App\Services\FileHandler;
use App\Services\LogService;
use App\Services\Request\ParametersValidator;
use App\Services\Request\RequestParameters;
use App\Services\Request\ResponseHandler;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class OrgController
 * @package App\Controller
 * @Route("/org", name="org")
 */
class OrgController extends AbstractController
{
    private RequestParameters $parameters;
    private ResponseHandler $responseHandler;
    private ParametersValidator $validator;
    protected EntityManagerInterface $entityManager;
    protected FileHandler $fileHandler;
    private LogService $logger;
    private OrgHandler $orgHandler;
    private UserHandler $userHandler;
    private ProjectHandler $projectHandler;
    private ActivityHandler $activityHandler;

    /**
     * OrgController constructor.
     * @param RequestParameters $requestParameters
     * @param ResponseHandler $responseHandler
     * @param ParametersValidator $validator
     * @param EntityManagerInterface $entityManager
     * @param FileHandler $fileHandler
     * @param LogService $logger
     * @param OrgHandler $orgHandler
     * @param UserHandler $userHandler
     * @param ProjectHandler $projectHandler
     * @param ActivityHandler $activityHandler
     */
    public function __construct(RequestParameters $requestParameters, ResponseHandler $responseHandler, ParametersValidator $validator, EntityManagerInterface $entityManager, FileHandler $fileHandler, LogService $logger, OrgHandler $orgHandler, UserHandler $userHandler, ProjectHandler $projectHandler, ActivityHandler $activityHandler)
    {
        $this->parameters = $requestParameters;
        $this->responseHandler = $responseHandler;
        $this->validator = $validator;
        $this->entityManager = $entityManager;
        $this->fileHandler = $fileHandler;

        $this->userHandler = $userHandler;
        $this->logger = $logger;

        $this->orgHandler = $orgHandler;
        $this->projectHandler = $projectHandler;
        $this->activityHandler = $activityHandler;
    }

    /**
     * @Route("", name="_registration", methods="post")
     * @param Request $request
     *      required Request Data ["name", "type", "email"]
     *      optionnals Request Data ["phone", 'description', "pictureFile"]
     * @return Response
     */
    public function create(Request $request): Response
    {
        try {
            // recover all data's request
            $this->parameters->setData($request);
            $this->parameters->addParam("referent", $this->getUser());

            $newOrg = $this->orgHandler->createOrg($this->getUser(), $this->parameters->getAllData());

            $newOrg = $this->orgHandler->withPictures([$newOrg]);

            return $this->responseHandler->successResponse($newOrg, "read_org");
        } catch (PartialContentException $e) {
            $this->logger->logError($e, $this->getUser(), "error");
            return $this->responseHandler->partialResponse($e, "read_org");
        } catch (ViolationException $e) {
            $this->logger->logError($e, $this->getUser(), "error");
            return $this->responseHandler->BadRequestResponse($e->getMessage());
        } catch (UniqueConstraintViolationException $e) {
            $this->logger->logError($e, $this->getUser(), "error");
            return $this->responseHandler->BadRequestResponse(json_encode(["name" => "Organization's name already exist"]));
        } catch (Exception $e) {
            $this->logger->logError($e, $this->getUser(), "error");
            return $this->responseHandler->serverErrorResponse("An error occured");
        }
    }


    /**
     * @Route("/public", name="_get_public", methods="get")
     * @param Request $request
     * @return Response
     */
    public function getPublic(Request $request): Response
    {
        try {
            // recover all data's request
            $this->parameters->setData($request);
            $this->parameters->addParam("access", "public");

            $orgs = $this->orgHandler->getOrgs(null, $this->parameters->getAllData());

            $orgs = $this->orgHandler->withPictures($orgs);

            return $this->responseHandler->successResponse($orgs, "read_org");
        } catch (Exception $e) {
            $this->logger->logError($e, $this->getUser(), "error");
            return $this->responseHandler->serverErrorResponse($e, "An error occured");
        }

    }

    /**
     * @Route("", name="_get_private", methods="get")
     * @param Request $request
     * @return Response
     */
    public function getPrivate(Request $request): Response
    {
        try {
            // recover all data's request
            $this->parameters->setData($request);

            //check if admin access required
            if($this->parameters->getData("admin")!== false){
                $this->denyAccessUnlessGranted('ROLE_ADMIN');
            }

            $orgs = $this->orgHandler->getOrgs($this->getUser(), $this->parameters->getAllData());

            $orgs = $this->orgHandler->withPictures($orgs);

            return $this->responseHandler->successResponse($orgs, "read_org");
        } catch (Exception $e) {//unexpected error
            $this->logger->logError($e, $this->getUser(), "error");
            return $this->responseHandler->serverErrorResponse("An error occurred");
        }
    }

    /**
     * @Route("/update", name="_put", methods="post")
     * @param Request $request
     * @return Response
     */
    public function updateOrganization(Request $request): Response
    {
        try {
            $this->parameters->setData($request);
            $this->parameters->hasData(["id"]);

            //check if admin access required
            if($this->parameters->getData("admin")!== false){
                $this->denyAccessUnlessGranted('ROLE_ADMIN');
                //change accessTable for access by id
                $accessTable["access"] = "search";
            }

            //retrieve org object
            $orgRepo = $this->entityManager->getRepository(Organization::class);
            $org = $orgRepo->find($this->parameters->getData("id"));
            if(is_null($org))throw new NoFoundException("organization id : ".$this->parameters->getData("id"));

            //handle potential link with an org
            $projectId = $this->parameters->getData("project");
            if ($projectId !== false) {
                $projectRepo = $this->entityManager->getRepository(Project::class);
                $project = $projectRepo->findOneBy(["id" => $projectId]);
                $this->parameters->putData("project", $project);
            }

            //handle potential link with an activity
            $actId = $this->parameters->getData("activity");
            if ($actId !== false) {
                $activity = null;
                if (is_numeric($actId)) {
                    $activityRepo = $this->entityManager->getRepository(Activity::class);
                    $activity = $activityRepo->findOneBy(["id" => $actId]);
                }
                $this->parameters->putData("activity", $activity);
            }

            $memberId = $this->parameters->getData("member");
            if($memberId !== false){
                $member = null;
                if(is_numeric($memberId)){
                    $userRepo = $this->entityManager->getRepository(User::class);
                    $member = $userRepo->find($memberId);
                }
                $this->parameters->putData("member", $member);
            }

            $org = $this->orgHandler->updateOrg($this->getUser(), $org, $this->parameters->getAllData());

            $org = $this->orgHandler->withPictures([$org]);

            return $this->responseHandler->successResponse($org, "read_org");
        } catch (ViolationException | NoFoundException $e) {
            $this->logger->logError($e, $this->getUser(), "error");
            return $this->responseHandler->BadRequestResponse($e->getMessage());
        } catch (Exception $e) {//unexpected error
            $this->logger->logError($e, $this->getUser(), "error");
            return $this->responseHandler->serverErrorResponse("An error occurred");
        }
    }


    /**
     * @param Request $request
     * need orgId and userId
     * if the user already in the membership of the organization, he will be remove.
     * @return Response
     * @Route("/putMember", name="_putMember", methods="put")
     */
    public function updateMembership(Request $request): Response
    {

        try {
            // recover all data's request
            $this->parameters->setData($request);

            $user = $this->userHandler->getUsers(null, ["access" => $this->parameters->getData("userId")])[0];

            $org = $this->orgHandler->getOrgs(
                $this->getUser(),
                [
                    "access" => "owned",
                    "id" => $this->parameters->getData("orgId")
                ]
            )[0];

            $org = $this->orgHandler->putMember($org, $user);
            $org = $this->orgHandler->withPictures([$org]);

            return $this->responseHandler->successResponse($org, "read_org");
//todo other exception
        } catch (Exception $e) {
            $this->logger->logError($e, $this->getUser(), "error");
            return $this->responseHandler->serverErrorResponse("An error occured");
        }
    }
}
