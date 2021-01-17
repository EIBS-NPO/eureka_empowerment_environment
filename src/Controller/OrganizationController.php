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
use Psr\Log\LoggerInterface;
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

    private LoggerInterface $logger;

    /**
     * UserController constructor.
     * @param RequestSecurity $requestSecurity
     * @param RequestParameters $requestParameters
     * @param EntityManagerInterface $entityManager
     * @param ParametersValidator $paramValidator
     * @param LoggerInterface $logger
     */
    public function __construct(RequestSecurity $requestSecurity, RequestParameters $requestParameters, EntityManagerInterface $entityManager, ParametersValidator $paramValidator, LoggerInterface $logger){
        $this->requestSecurity = $requestSecurity;
        $this->requestParameters = $requestParameters;
        $this->entityManager = $entityManager;
        $this->paramValidator = $paramValidator;
        $this->logger = $logger;
    }

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
        }catch(SecurityException $e){
            $this->logger->warning($e);
            return new Response(
                json_encode(["error" => "ACCESS_FORBIDDEN"]),
                Response::HTTP_FORBIDDEN,
                ["Content-Type" => "application/json"]);
        }

        //recover parameters of the request in an array $data
        $data = $this->requestParameters->getData($request);

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

        //todo check email non unique mais unique par name
        //request's required & optional Field
        $requiredFields = ["type", "name", "email"];
        $optionalFields = ["phone", "address"];
        $this->paramValidator->initValidator($requiredFields, $optionalFields,Organization::class, $data);

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

        //create user object
        $org = new Organization();

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
            $this->logger->error($e);
            return new Response(
                json_encode(["error" => "ERROR_SERVER"]),
                $e->getCode(),
                ["Content-Type" => "application/json"]
            );
        }

        $this->logger->info("User with id : " .$user->getId(). " has created an Organization with id : " .$org->getId());
        return new Response(
            json_encode(["success" => true]),
            Response::HTTP_OK,
            ["Content-Type" => "application/json"]
        );
    }

    /**
     * @Route("/organization", name="get_refered_organization", methods="get")
     * @param Request $request
     * @return Response
     */
    public function getOrganization(Request $request){
        //cleanXSS
        try {
            $request = $this->requestSecurity->cleanXSS($request);
        }catch(\Exception $e) {
            $this->logger->warning($e);
            return new Response(
                json_encode(["error" => "ACCESS_FORBIDDEN"]),
                Response::HTTP_FORBIDDEN,
                ["Content-Type" => "application/json"]);
        }

        //recover parameters of the request in an array $data
        $data = $this->requestParameters->getData($request);

        try{
            if(isset($data['id']) && !is_numeric($data['id'])){
                throw new Exception("id parameter must be numeric", Response::HTTP_BAD_REQUEST);
            }
        }catch(\Exception $e){
            $this->logger->info($e);
            return new Response(
                json_encode(["error" => $e->getMessage()]),
                $e->getCode(),
                ["Content-Type" => "application/json"]
            );
        }

        $orgRepository = $this->entityManager->getRepository(Organization::class);
        try{
            //query for organizationData
            if(count($data) > 0 && (isset($data['id']))){
                $orgData = $orgRepository->findBy(['id' => $data['id']]);
            }else { //otherwise we return all users
                $orgData = $orgRepository->findAll();
            }
        }
        catch(\Exception $e){
            $this->logger->error($e);
            return new Response(
                json_encode(["error" => "ERROR_SERVER"]),
                $e->getCode(),
                ["Content-Type" => "application/json"]
            );
        }

        //serialize all found data
        if (count($orgData) > 0 ) {
            foreach($orgData as $key => $org){
                $orgData[$key] = $org->serialize("read_organization");
            }
        }

        //success
        return new Response(
            json_encode(["success" => true, "data" => $orgData]),
            Response::HTTP_OK,
            ["content-type" => "application/json"]
        );
    }

    /*public function getReferedOrganization(Request $request){
        //cleanXSS
        try {
            $request = $this->requestSecurity->cleanXSS($request);
        }catch(\Exception $e) {
            $this->logger->warning($e);
            return new Response(
                json_encode(["error" => "ACCESS_FORBIDDEN"]),
                Response::HTTP_FORBIDDEN,
                ["Content-Type" => "application/json"]);
        }

        $orgRepository = $this->entityManager->getRepository(Organization::class);
        try{
            $orgData = $orgRepository->findBy(['referent' => $this->getUser()->getId()]);
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
        if (count($orgData) > 0 ) {
            foreach($orgData as $key => $org){
                $orgData[$key] = $org->serialize("read_referent");
            }
        }

        //success
        return new Response(
            json_encode(["data" => $orgData]),
            Response::HTTP_OK,
            ["content-type" => "application/json"]
        );
    }*/
}
