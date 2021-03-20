<?php

namespace App\Controller;

use App\Entity\Address;
use App\Entity\Organization;
use App\Entity\User;
use App\Exceptions\SecurityException;
use App\Exceptions\ViolationException;
use App\Service\FileHandler;
use App\Service\LogService;
use App\Service\Request\ParametersValidator;
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
 * Class AddressController
 * @package App\Controller
 * @Route("/address", name="address")
 */
class AddressController extends CommonController
{
    private RequestSecurity $security;
    private RequestParameters $parameters;
    private ResponseHandler $responseHandler;
    private ParametersValidator $validator;
    protected EntityManagerInterface $entityManager;
    protected FileHandler $fileHandler;
    private LogService $logger;

    /**
     * UserController constructor.
     * @param RequestSecurity $requestSecurity
     * @param RequestParameters $requestParameters
     * @param ResponseHandler $responseHandler
     * @param ParametersValidator $validator
     * @param EntityManagerInterface $entityManager
     * @param FileHandler $fileHandler
     * @param LogService $logger
     */
    public function __construct(RequestSecurity $requestSecurity, RequestParameters $requestParameters, ResponseHandler $responseHandler, ParametersValidator $validator, EntityManagerInterface $entityManager, FileHandler $fileHandler, LogService $logger)
    {
        $this->security = $requestSecurity;
        $this->parameters = $requestParameters;
        $this->responseHandler = $responseHandler;
        $this->validator = $validator;
        $this->entityManager = $entityManager;
        $this->fileHandler = $fileHandler;
        $this->logger = $logger;
    }

    /**
     * @Route("", name="_post", methods="post")
     * @param Request $request
     * @return Response
     */
    public function post(Request $request): Response
    {
        try{$this->security->cleanXSS($request);}
        catch(SecurityException $e) {
            $this->logger->logError($e, $this->getUser(), "warning");
            return $this->responseHandler->forbidden();
        }

        // recover all data's request
        $this->parameters->setData($request);

        try{
            //address for an org
            if ($this->parameters->getData('orgId') !== false) {
                $repository = $this->entityManager->getRepository(Organization::class);
                $data = $repository->findBy(["id" => $this->parameters->getData('orgId')]);
                if(count($data) === 0 ){
                    $this->logger->logInfo(" Organization with id : ". $this->parameters->getData("orgId") ." not found " );
                    return $this->responseHandler->notFoundResponse();
                }
                $this->parameters->addParam("ownerType", "Organization");
                $this->parameters->addParam("orgOwner", $data[0]);
            }
            else { //address for an user
                $repository = $this->entityManager->getRepository(User::class);
                $data = $repository->findBy(["id" => $this->parameters->getData('userId')]);
                if(count($data) === 0 ){
                    $this->logger->logInfo(" User with id : ". $this->parameters->getData("userId") ." not found " );
                    return $this->responseHandler->notFoundResponse();
                }
                $this->parameters->addParam("ownerType", "User");
                $this->parameters->addParam("owner", $data[0]);
            }


            //check params Validations
            try{ $this->validator->isInvalid(
                ["address", "country", "city", "zipCode", "ownerType"],
                ["complement", "latitude", "longitude", "owner", "orgOwner"],
                Address::class);
            } catch(ViolationException $e){
                $this->logger->logError($e, $this->getUser(), "error");
                return $this->responseHandler->BadRequestResponse($e->getViolationsList());
            }

            //create project object && set validated fields
            $address = new Address();
            foreach( ["address", "country", "city", "zipCode", "complement", "latitude", "longitude", "owner", "orgOwner", "ownerType"]
                     as $field ) {
                if($this->parameters->getData($field) !== false ) {
                    $setter = 'set'.ucfirst($field);
                    $address->$setter($this->parameters->getData($field));
                }
            }

            $this->entityManager->persist($address);
            $this->entityManager->flush();

            return $this->responseHandler->successResponse([$address]);

        }catch(Exception $e){
            $this->logger->logError($e,$this->getUser(),"error");
            return $this->responseHandler->serverErrorResponse($e, "An error occured");
        }
    }

    /**
     * @param Request $request
     * @return Response|null
     * @Route("", name="_update", methods="put")
     */
    public function update(Request $request) :Response {
        try{$this->security->cleanXSS($request);}
        catch(SecurityException $e) {
            $this->logger->logError($e, $this->getUser(), "warning");
            return $this->responseHandler->forbidden();
        }

        // recover all data's request
        $this->parameters->setData($request);

        //check if required params exist
        try{ $this->parameters->hasData(["id"]); }
        catch(ViolationException $e) {
            $this->logger->logError($e, $this->getUser(), "error");
            return $this->responseHandler->BadRequestResponse($e->getViolationsList());
        }

        //check params Validations
        try{ $this->validator->isInvalid(
            [],
            ["address", "country", "city", "zipCode","complement", "latitude", "longitude"],
            Address::class);
        } catch(ViolationException $e){
            $this->logger->logError($e, $this->getUser(), "error");
            return $this->responseHandler->BadRequestResponse($e->getViolationsList());
        }

        try{
            $repository = $this->entityManager->getRepository(Address::class);
            $data = $repository->findBy(["id" => $this->parameters->getData('id')]);
            if(count($data) === 0 ){
                $this->logger->logInfo(" Address with id : ". $this->parameters->getData("id") ." not found " );
                return $this->responseHandler->notFoundResponse();
            }
            $address = $data[0];

            if($this->getUser()->getRoles()[0] !== "ROLE_ADMIN"){
                if(!$this->isOwner($data[0])) return $this->responseHandler->unauthorizedResponse("unauthorized");
            }

            foreach( ["address", "country", "city", "zipCode", "complement", "latitude", "longitude", "owner", "orgOwner", "ownerType"]
                     as $field ) {
                if($this->parameters->getData($field) !== false ) {
                    $setter = 'set'.ucfirst($field);
                    $address->$setter($this->parameters->getData($field));
                }
            }

            $this->entityManager->flush();

            return $this->responseHandler->successResponse([$address]);

        }catch(Exception $e){
            $this->logger->logError($e,$this->getUser(),"error");
            return $this->responseHandler->serverErrorResponse($e, "An error occured");
        }
    }


    /**
     * @param Request $insecureRequest
     * @return Response
     * @Route("", name="_get", methods="get")
     */
    public function getAddress(Request $request) :Response {
        try{$this->security->cleanXSS($request);}
        catch(SecurityException $e) {
            $this->logger->logError($e, $this->getUser(), "warning");
            return $this->responseHandler->forbidden();
        }

        // recover all data's request
        $this->parameters->setData($request);

        $crit = null;
        if($this->parameters->getData('id') !== false ) {
            $crit = $this->parameters->getData('id');
        }

        try{
            $repository = $this->entityManager->getRepository(Address::class);
            $data = $repository->findBy(["id" => $this->parameters->getData('id')]);
            if(count($data) === 0 ){
                $this->logger->logInfo(" Address with id : ". $this->parameters->getData("id") ." not found " );
                return $this->responseHandler->notFoundResponse();
            }

            return $this->responseHandler->successResponse($data);

        }catch(Exception $e){
            $this->logger->logError($e,$this->getUser(),"error");
            return $this->responseHandler->serverErrorResponse($e, "An error occured");
        }
    }


    /**
     * @param Request $request
     * @return Response
     * @Route("", name="_delete", methods="delete")
     */
    public function remove(Request $request){
        try{$this->security->cleanXSS($request);}
        catch(SecurityException $e) {
            $this->logger->logError($e, $this->getUser(), "warning");
            return $this->responseHandler->forbidden();
        }

        // recover all data's request
        $this->parameters->setData($request);

     //check if required params exist
        try{ $this->parameters->hasData(["id"]); }
        catch(ViolationException $e) {
            $this->logger->logError($e, $this->getUser(), "error");
            return $this->responseHandler->BadRequestResponse($e->getViolationsList());
        }

        try{
            $repository = $this->entityManager->getRepository(Address::class);
            $data = $repository->findBy(["id" => $this->parameters->getData('id')]);
            if(count($data) === 0 ){
                $this->logger->logInfo(" Address with id : ". $this->parameters->getData("id") ." not found " );
                return $this->responseHandler->notFoundResponse();
            }

            if($this->getUser()->getRoles()[0] !== "ROLE_ADMIN") {
                if (!$this->isOwner($data[0])) return $this->responseHandler->unauthorizedResponse("unauthorized");
            }

            $this->entityManager->remove($data[0]);
            $this->entityManager->flush();

            return $this->responseHandler->successResponse(["success"]);

        }catch(Exception $e){
            $this->logger->logError($e,$this->getUser(),"error");
            return $this->responseHandler->serverErrorResponse($e, "An error occured");
        }
    }


    /**
     * @param $address
     * @return bool
     */
    public function isOwner($address){
        if(
            ($address->getOwner() !== null && $address->getOwner()->getId() !== $this->getUser()->getId())
            || ($address->getOrgOwner() !== null && $address->getOrgOwner()->getReferent()->getId() !== $this->getUser()->getId())
        ){
            return false;
        }
        return true;
    }
}
