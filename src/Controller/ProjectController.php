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
     * UserController constructor.
     * @param RequestSecurity $requestSecurity
     * @param RequestParameters $requestParameters
     * @param EntityManagerInterface $entityManager
     * @param ParametersValidator $paramValidator
     */
    public function __construct(RequestSecurity $requestSecurity, RequestParameters $requestParameters, EntityManagerInterface $entityManager, ParametersValidator $paramValidator){
        $this->requestSecurity = $requestSecurity;
        $this->requestParameters = $requestParameters;
        $this->entityManager = $entityManager;
        $this->paramValidator = $paramValidator;
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
        }catch(SecurityException $exception){
            return new Response(
                json_encode(["success" => false, "error" => "Potential attack has been detected"]),
                Response::HTTP_FORBIDDEN,
                ["Content-Type" => "application/json"]);
        }

        //recover parameters of the request in an array $data
        $data = $this->requestParameters->getData($request);

        //request's required & optional Field
        $requiredFields = ["creatorId", "title", "description", "startDate"];
        $optionalFields = ["endDate", "orgId"];

        //validation user's id and recover userObject
        //todo dispatch in a methods
        try{
            if (!isset($data['creatorId'])) {
                throw new Exception("creatorId required for new organization's referent", Response::HTTP_BAD_REQUEST);
            }
            if(!is_numeric($data['creatorId'])){
                throw new Exception("creatorId must be numeric", Response::HTTP_BAD_REQUEST);
            }

            $user = $this->entityManager->getRepository(User::class)->find($data["creatorId"]);
            if ($user == null) {
                throw new Exception("user not found", Response::HTTP_NOT_FOUND);
            }
        }catch(\Exception $e){
            return new Response(
                json_encode(["success" => false, "error" => $e->getMessage()]),
                $e->getCode(),
                ["Content-Type" => "application/json"]
            );
        }

        //validation startDate & convert to date object
        //todo dispatch in method or a dateService?
        try{
            if (!isset($data['startDate'])) {
                //todo DateTime now()
                throw new Exception("startDate required for new project", Response::HTTP_BAD_REQUEST);
            }
            //^\d\d\d\d-(0?[1-9]|1[0-2])-(0?[1-9]|[12][0-9]|3[01])$
            if(!preg_match("#^\d\d\d\d-(0?[1-9]|1[0-2])-(0?[1-9]|[12][0-9]|3[01])$#", $data['startDate'])){
                throw new Exception("the startDate must be in YYYY-MM-DD format", Response::HTTP_BAD_REQUEST);
            }

            $data['startDate'] = new DateTime ($data["startDate"]);

        }catch(\Exception $e){
            return new Response(
                json_encode(["success" => false, "error" => $e->getMessage()]),
                $e->getCode(),
                ["Content-Type" => "application/json"]
            );
        }

        //validation endDate & convert to date object
        //todo control period with startDate
        //todo dispatch in method or a dateService?
        if (isset($data['endDate'])) {
            try {
                if (!preg_match("#^\d\d\d\d-(0?[1-9]|1[0-2])-(0?[1-9]|[12][0-9]|3[01])$#", $data['endDate'])) {
                    throw new Exception("the endDate must be in YYYY-MM-DD format", Response::HTTP_BAD_REQUEST);
                }

                $data['endDate'] = new DateTime ($data["endDate"]);

            } catch (\Exception $e) {
                return new Response(
                    json_encode(["success" => false, "error" => $e->getMessage()]),
                    $e->getCode(),
                    ["Content-Type" => "application/json"]
                );
            }
        }

        //validation orgId & convert to Organization object
        //todo control period with startDate
        //todo dispatch in method or a dateService?
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
                return new Response(
                    json_encode(["success" => false, "error" => $e->getMessage()]),
                    $e->getCode(),
                    ["Content-Type" => "application/json"]
                );
            }
        }

        //create project object
        $project = new Project();

        //Validate required fields
        try{
            $violationsList = $violationsList = $this->paramValidator->fieldsValidation(
                "project",
                $requiredFields,
                true,
                $data
            );
        }catch(Exception $e){
            return new Response(
                json_encode(["success" => false, "error" => $e->getMessage()]),
                Response::HTTP_BAD_REQUEST,
                ["Content-Type" => "application/json"]
            );
        }


        //validate optional field
        try{
            $violationsList = array_merge(
                $violationsList,
                $violationsList = $this->paramValidator->fieldsValidation(
                    "project",
                    $optionalFields,
                    false,
                    $data)
            );
        }catch(Exception $e){
            return new Response(
                json_encode(["success" => false, "error" => $e->getMessage()]),
                Response::HTTP_BAD_REQUEST,
                ["Content-Type" => "application/json"]
            );
        }

        //return violations
        if( count($violationsList) > 0 ){
            return new Response(
                json_encode(["success" => false, "error" => $violationsList]),
                Response::HTTP_BAD_REQUEST,
                ["Content-Type" => "application/json"]
            );
        }

        //set organization's validated fields
        $project->setCreator($user);
        $project->setTitle($data["title"]);
        $project->setDescription($data["description"]);
        $project->setStartDate($data["startDate"]);

        //set organization's validated optional fields
        //todo organization (with id)
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
            return new Response(
                json_encode(["success" => false, "error" => $e->getMessage()]),
                $e->getCode(),
                ["Content-Type" => "application/json"]
            );
        }

        //success
        return new Response(
            json_encode(["success" => true]),
            Response::HTTP_OK,
            ["Content-Type" => "application/json"]
        );
    }

    /**
     * @Route("/project", name="get_project", methods="get")
     * @param Request $request
     * @return Response
     */
    public function getProject(Request $request): Response {
        //cleanXSS
        try {
            $request = $this->requestSecurity->cleanXSS($request);
        }catch(\Exception $e) {
            return new Response(
                json_encode(["success" => false, "error" => "Potential attack has been detected"]),
                Response::HTTP_FORBIDDEN,
                ["Content-Type" => "application/json"]);
        }

        //recover parameters of the request in an array $data
        $data = $this->requestParameters->getData($request);

        $projectRepository = $this->entityManager->getRepository(Project::class);
        try{
            //verifies the existence of userId or email field for query by criteria
            if(count($data) > 0 && (isset($data['creatorId']) || isset($data['orgId']))){
                if(isset($data["creatorId"])){$criteria = ['creatorId' => $data['creatorId']];}
                elseif(isset($data["orgId"])){$criteria = ['organizationId' => $data['orgId']];}
                $projectData = $projectRepository->findBy($criteria);
            }else { //otherwise we return all users
                $projectData = $projectRepository->findAll();
            }
        }
        catch(\Exception $e){
            return new Response(
                json_encode(["success" => false, "error" => $e->getMessage()]),
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
        else {
            return new Response(
                json_encode(["success" => false, "error" => "User Not Found"]),
                Response::HTTP_NOT_FOUND,
                ["Content-Type" => "application/json"]
            );
        }

        //success
        return new Response(
            json_encode(["success" => true, "data" => $projectData]),
            Response::HTTP_OK,
            ["content-type" => "application/json"]
        );
    }
}
