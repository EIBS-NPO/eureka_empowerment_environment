<?php

namespace App\Controller;

use App\Entity\Organization;
use App\Entity\Project;
use App\Entity\User;
use App\Exceptions\SecurityException;
use App\Service\Request\ParametersValidator;
use App\Service\Request\RequestParameters;
use App\Service\Security\RequestSecurity;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ProjectController extends AbstractController
{
    private RequestSecurity $requestSecurity;

    private RequestParameters $requestParameters;

    private EntityManagerInterface $entityManager;

    private ParametersValidator $paramValidator;
    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * UserController constructor.
     * @param RequestSecurity $requestSecurity
     * @param RequestParameters $requestParameters
     * @param EntityManagerInterface $entityManager
     * @param ParametersValidator $paramValidator
     */
    public function __construct(RequestSecurity $requestSecurity, RequestParameters $requestParameters, EntityManagerInterface $entityManager, ParametersValidator $paramValidator, LoggerInterface $logger){
        $this->requestSecurity = $requestSecurity;
        $this->requestParameters = $requestParameters;
        $this->entityManager = $entityManager;
        $this->paramValidator = $paramValidator;
        $this->logger = $logger;
    }

    //todo access role?
    /**
     * @Route("/project/create", name="create_project", methods="post")
     * @param Request $request
     * @return Response
     */
    public function create(Request $request): Response
    {
        //cleanXSS
        try{
            $request = $this->requestSecurity->cleanXSS($request);
        }catch(SecurityException $e){
            $this->logger->warning($e);
            return new Response(
                json_encode(["error" => "ACCESS_FORBIDDEN"]),
                Response::HTTP_FORBIDDEN,
                ["Content-Type" => "application/json"]);
        }

        //recover parameters of the request in an array $data
        $data = $this->requestParameters->getData($request);

        //validation orgId & convert to Organization object
        if (isset($data['orgId'])) {
            try {
                if(!is_numeric($data['orgId'])){
                    throw new Exception("orgId must be numeric", Response::HTTP_BAD_REQUEST);
                }

                $org = $this->entityManager->getRepository(Organization::class)->find($data["orgId"]);
                if ($org == null) {
                    throw new Exception("organization not found", Response::HTTP_NOT_FOUND);
                }

                $data['organization'] = $org;
                array_push($optionalFields, "organization");
                unset($optionalFields['orgId']);
                unset($data["orgId"]);

            } catch (\Exception $e) {
                $this->logger->error($e);
                return new Response(
                    json_encode(["error" => "BAD_REQUEST"]),
                    Response::HTTP_BAD_REQUEST,
                    ["Content-Type" => "application/json"]
                );
            }
        }

        //validation user's id and recover userObject
        try{
            $user = $this->entityManager->getRepository(User::class)->find($this->getUser()->getId());
            if ($user == null) {
                return new Response(
                    json_encode(["error" => "DATA_NOT_FOUND"]),
                    Response::HTTP_NOT_FOUND,
                    ["Content-Type" => "application/json"]
                );
            }
        }catch(\Exception $e){
            $this->logger->error($e);
            return new Response(
                json_encode(["error" => "ERROR_SERVER"]),
                Response::HTTP_INTERNAL_SERVER_ERROR,
                ["Content-Type" => "application/json"]
            );
        }

        //validation startDate & convert to date object
        //todo startDate must be a future date
        try{
            if (isset($data['startDate'])) {
                if (!preg_match("#^\d\d\d\d-(0?[1-9]|1[0-2])-(0?[1-9]|[12][0-9]|3[01])$#", $data['startDate'])) {
                    throw new Exception("the startDate must be in YYYY-MM-DD format", Response::HTTP_BAD_REQUEST);
                }
                $data['startDate'] = new DateTime ($data["startDate"]);
            }
            else {
                $data['startDate'] = new DateTime("now");
            }
        }catch(\Exception $e){
            $this->logger->info($e);
            return new Response(
                json_encode(["error" => $e->getMessage()]),
                $e->getCode(),
                ["Content-Type" => "application/json"]
            );
        }

        //validation endDate & convert to date object
        //todo control period with startDate, only posterior to the startDate
        if (isset($data['endDate'])) {
            try {
                if (!preg_match("#^\d\d\d\d-(0?[1-9]|1[0-2])-(0?[1-9]|[12][0-9]|3[01])$#", $data['endDate'])) {
                    throw new Exception("the endDate must be in YYYY-MM-DD format", Response::HTTP_BAD_REQUEST);
                }

                $data['endDate'] = new DateTime ($data["endDate"]);

            } catch (\Exception $e) {
                $this->logger->info($e);
                return new Response(
                    json_encode(["error" => $e->getMessage()]),
                    $e->getCode(),
                    ["Content-Type" => "application/json"]
                );
            }
        }

        //create project object
        $project = new Project();

        //request's required & optional Field
        $requiredFields = ["creatorId", "title", "description", "startDate"];
        $optionalFields = ["endDate", "orgId"];
        $this->paramValidator->initValidator($requiredFields,$optionalFields,User::class, $data);

        //Validate required fields
        try{
            $violationsList = $this->paramValidator->checkViolations();
        }catch(Exception $e){
            $this->logger->error($e);
            return new Response(
                json_encode(["error" => "ERROR_SERVER"]),
                Response::HTTP_INTERNAL_SERVER_ERROR,
                ["Content-Type" => "application/json"]
            );
        }

        //return violations
        if( count($violationsList) > 0 ){
            return new Response(
                json_encode(["error" => $violationsList]),
                Response::HTTP_BAD_REQUEST,
                ["Content-Type" => "application/json"]
            );
        }

        //set organization's validated fields
        $project->setCreator($user);
        $project->setTitle($data["title"]);
        $project->setDescription($data["description"]);
        $project->setStartDate($data["startDate"]);
        $project->setIsPublic(false);

        //set project's validated optional fields [endDate or organization]
        foreach($optionalFields as $field){
            if(isset($data[$field])){
                $setter = "set".ucfirst($field);
                $project->$setter($data[$field]);
            }
        }

        //persist the new project
        try{
            $this->entityManager->persist($project);
            $this->entityManager->flush();
        }catch(Exception $e){
            $this->logger->error($e);
            return new Response(
                json_encode(["error" => "ERROR_SERVER"]),
                Response::HTTP_INTERNAL_SERVER_ERROR,
                ["Content-Type" => "application/json"]
            );
        }

        //success
        $this->logger->info("User with id : " .$user->getId(). " has created a Project with id : " .$project->getId());
        return new Response(
            json_encode(["success" => true]),
            Response::HTTP_OK,
            ["Content-Type" => "application/json"]
        );
    }

    /**
     * @Route("/project/update", name="update_project", methods="put")
     * @param Request $request
     * @return Response
     */
    public function updateProject (Request $request) :Response
    {
        //cleanXSS
        try {
            $request = $this->requestSecurity->cleanXSS($request);
        } catch (\Exception $e) {
            $this->logger->warning($e);
            return new Response(
                json_encode(["error" => "ACCESS_FORBIDDEN"]),
                Response::HTTP_FORBIDDEN,
                ["Content-Type" => "application/json"]);
        }

        //recover parameters of the request in an array $data
        $data = $this->requestParameters->getData($request);

        if (!isset($data["projectId"])) {
            $this->logger->info(Response::HTTP_NOT_FOUND . "missed param: projectId");
            return new Response(
                json_encode(["error" => "project not found"]),
                Response::HTTP_NOT_FOUND,
                ["Content-Type" => "application/json"]
            );
        }

        //validation project's id and recover projectObject
        $projectRepository = $this->entityManager->getRepository(Project::class);
        try {
            $project = $projectRepository->find($data["projectId"]);
            if ($project == null) {
                $this->logger->error(Response::HTTP_NOT_FOUND . " | Project with id :" . $data["projectId"] . ", Not found.");
                return new Response(
                    json_encode(["error" => "project not found"]),
                    Response::HTTP_NOT_FOUND,
                    ["Content-Type" => "application/json"]
                );
            }


            //todo replace in a service?
            //check if the user is the creator
            if ($project->getCreator()->getId() != $this->getUser()->getId()) {
                $this->logger->warning("the user with id : " . $this->getUser()->getId() . " tries to modify a project that does not belong to him and to which he is not assigned");
                return new Response(
                    json_encode(["error" => "ACCESS_FORBIDDEN"]),
                    Response::HTTP_FORBIDDEN,
                    ["Content-Type" => "application/json"]
                );
            }
            //todo check if the user is assigned

        } catch (Exception $e) {
            $this->logger->error($e);
            return new Response(
                json_encode(["error" => "ERROR_SERVER"]),
                $e->getCode(),
                ["Content-Type" => "application/json"]
            );
        }

        //validation orgId & convert to Organization object
        if (isset($data['orgId'])) {
            try {
                if (!is_numeric($data['orgId'])) {
                    throw new Exception("orgId must be numeric", Response::HTTP_BAD_REQUEST);
                }

                $org = $this->entityManager->getRepository(Organization::class)->find($data["orgId"]);
                if ($org == null) {
                    throw new Exception("organization not found", Response::HTTP_NOT_FOUND);
                }

                $data['organization'] = $org;

            } catch (\Exception $e) {
                $this->logger->error($e);
                return new Response(
                    json_encode(["error" => "BAD_REQUEST"]),
                    Response::HTTP_BAD_REQUEST,
                    ["Content-Type" => "application/json"]
                );
            }
        }

        //validation startDate & convert to date object
        //todo check only future dates
        try {
            if (isset($data['startDate'])) {
                if (!preg_match("#^\d\d\d\d-(0?[1-9]|1[0-2])-(0?[1-9]|[12][0-9]|3[01])$#", $data['startDate'])) {
                    throw new Exception("the startDate must be in YYYY-MM-DD format", Response::HTTP_BAD_REQUEST);
                }
                $data['startDate'] = new DateTime ($data["startDate"]);
            } else {
                $data['startDate'] = new DateTime("now");
            }
        } catch (\Exception $e) {
            $this->logger->info($e);
            return new Response(
                json_encode(["error" => $e->getMessage()]),
                $e->getCode(),
                ["Content-Type" => "application/json"]
            );
        }

        //validation endDate & convert to date object
        //todo control period with startDate, endate must be posterior
        if (isset($data['endDate'])) {
            try {
                if (!preg_match("#^\d\d\d\d-(0?[1-9]|1[0-2])-(0?[1-9]|[12][0-9]|3[01])$#", $data['endDate'])) {
                    throw new Exception("the endDate must be in YYYY-MM-DD format", Response::HTTP_BAD_REQUEST);
                }

                $data['endDate'] = new DateTime ($data["endDate"]);

            } catch (\Exception $e) {
                $this->logger->info($e);
                return new Response(
                    json_encode(["error" => $e->getMessage()]),
                    $e->getCode(),
                    ["Content-Type" => "application/json"]
                );
            }
        }

        //request's required & optional Field
        $optionalFields = ["title", "description", "startDate", "endDate", "isPublic", "organization"];
        $this->paramValidator->initValidator(null, $optionalFields, User::class, $data);

        //Validate required fields
        try {
            $violationsList = $this->paramValidator->checkViolations();
        } catch (Exception $e) {
            $this->logger->error($e);
            return new Response(
                json_encode(["error" => "ERROR_SERVER"]),
                Response::HTTP_INTERNAL_SERVER_ERROR,
                ["Content-Type" => "application/json"]
            );
        }

        //return violations
        if (count($violationsList) > 0) {
            return new Response(
                json_encode(["error" => $violationsList]),
                Response::HTTP_BAD_REQUEST,
                ["Content-Type" => "application/json"]
            );
        }

        //set project's validated optional fields [endDate or organization]
        foreach ($optionalFields as $field) {
            if (isset($data[$field])) {
                $setter = "set" . ucfirst($field);
                $project->$setter($data[$field]);
            }
        }

        //persist updated project
        try {
            $this->entityManager->flush();
        } catch (Exception $e) {
            $this->logger->error($e);
            return new Response(
                json_encode(["error" => "ERROR_SERVER"]),
                Response::HTTP_INTERNAL_SERVER_ERROR,
                ["Content-Type" => "application/json"]
            );
        }

        //serialize
        if($project->getCreator()->getId() === $this->getUser()->getId()){
            $project = $project->serialize("read_by_creator");
        }
        else {
            $project = $project->serialize();
        }


        //success
        $this->logger->info("User with id : " . $this->getUser()->getId() . " has modified the Project with id : " . $data["projectId"]);
        return new Response(
            json_encode(["data" => $project]),
            Response::HTTP_OK,
            ["Content-Type" => "application/json"]
        );
    }

    /**
     * returns all public projects
     * @Route("/public/projects", name="get_public_projects", methods="get")
     * @param Request $request
     * @return Response
     */
    public function getProjects(Request $request): Response {
        //cleanXSS
        try {
            $request = $this->requestSecurity->cleanXSS($request);
        }catch(\Exception $e) {
            $this->logger->warning($e);
            return new Response(
                json_encode(["success" => false, "error" => "ACCESS_FORBIDDEN"]),
                Response::HTTP_FORBIDDEN,
                ["Content-Type" => "application/json"]);
        }

        $projectRepository = $this->entityManager->getRepository(Project::class);
        try{
            $projectData = $projectRepository->findBy(["isPublic" => true]);
        }
        catch(\Exception $e){
            $this->logger->error($e);
            return new Response(
                json_encode(["error" => "ERROR_SERVER"]),
                $e->getCode(),
                ["Content-Type" => "application/json"]
            );
        }

        //serialize all found userObject
        if (count($projectData) > 0 ) {
            foreach($projectData as $key => $project){
                $projectData[$key] = $project->serialize("read_project");
            }
        }

        //success
        return new Response(
            json_encode(["data" => $projectData]),
            Response::HTTP_OK,
            ["content-type" => "application/json"]
        );
    }

    /**
     * returns to a user his created projects
     * @Route("/projects/created", name="get_projects_created", methods="get")
     * @param Request $request
     * @return Response
     */
    public function getProjectsCreated(Request $request): Response {
        //cleanXSS
        try {
            $request = $this->requestSecurity->cleanXSS($request);
        }catch(\Exception $e) {
            $this->logger->warning($e);
            return new Response(
                json_encode(["success" => false, "error" => "ACCESS_FORBIDDEN"]),
                Response::HTTP_FORBIDDEN,
                ["Content-Type" => "application/json"]);
        }

        $projectRepository = $this->entityManager->getRepository(Project::class);
        try{
            $projectData = $projectRepository->findBy(["creator" => $this->getUser()->getId()]);
        }
        catch(\Exception $e){
            $this->logger->error($e);
            return new Response(
                json_encode(["error" => "ERROR_SERVER"]),
                $e->getCode(),
                ["Content-Type" => "application/json"]
            );
        }

        //serialize all found userObject
        if (count($projectData) > 0 ) {
            foreach($projectData as $key => $project){
                $projectData[$key] = $project->serialize("read_by_creator");
            }
        }

        //success
        return new Response(
            json_encode(["data" => $projectData]),
            Response::HTTP_OK,
            ["content-type" => "application/json"]
        );
    }

    //todo request for return projectfollowed by a user,
    // need check isPublic and if user is assigned if the project isn't public
}
