<?php

namespace App\Controller;

use App\Entity\Organization;
use App\Entity\User;
use App\Exceptions\SecurityException;
use App\Service\Request\ParametersValidator;
use App\Service\Request\RequestParameters;
use App\Service\Security\RequestSecurity;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use PhpParser\Node\Param;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class OrganizationController extends AbstractController
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
     * @Route("/organization/create", name="create_organization", methods="post")
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
        $requiredFields = ["userId", "type", "name", "email"];
        $optionalFields = ["phone", "address"];

        //validation user's id and recover userObject
        try{
            if (!isset($data['userId'])) {
                throw new Exception("UserId required for new organization's referent", Response::HTTP_BAD_REQUEST);
            }
            if(!is_numeric($data['userId'])){
                throw new Exception("userId must be numeric", Response::HTTP_BAD_REQUEST);
            }

            $user = $this->entityManager->getRepository(User::class)->find($data["userId"]);
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

        //create user object
        $org = new Organization();

        //Validate required fields
        try{
            $violationsList = $violationsList = $this->paramValidator->fieldsValidation(
                "organization",
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
                    "organization",
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
        $org->setReferent($user);
        $org->setEmail($data["email"]);
        $org->setName($data["name"]);
        $org->setType($data["type"]);

        //set organization's validated optional fields
        foreach($optionalFields as $field){
            if(isset($data[$field])){
                $setter = "set".ucfirst($field);
                $org->$setter($data[$field]);
            }
        }

        //persist the new organization
        try{
            $this->entityManager->persist($org);
            $this->entityManager->flush();
        }catch(Exception $e){
            return new Response(
                json_encode(["success" => false, "error" => $e->getMessage()]),
                $e->getCode(),
                ["Content-Type" => "application/json"]
            );
        }

        return new Response(
            json_encode(["success" => true]),
            Response::HTTP_OK,
            ["Content-Type" => "application/json"]
        );
    }

    /**
     * @Route("/organization", name="get_organization", methods="get")
     * @param Request $request
     * @return Response
     */
    public function getOrganization(Request $request){
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

        $orgRepository = $this->entityManager->getRepository(Organization::class);
        try{
            //verifies the existence of userId or email field for query by criteria
            if(count($data) > 0 && (isset($data['id']) || isset($data['email']))){
                $orgData = $orgRepository->findBy(
                    isset($data['id']) ? ['id' => $data['id']] : ['email' => $data['email']]
                );
            }else { //otherwise we return all users
                $orgData = $orgRepository->findAll();
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
        if (count($orgData) > 0 ) {
            foreach($orgData as $key => $org){
                $orgData[$key] = $org->serialize();
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
            json_encode(["success" => true, "data" => $orgData]),
            Response::HTTP_OK,
            ["content-type" => "application/json"]
        );
    }
}
