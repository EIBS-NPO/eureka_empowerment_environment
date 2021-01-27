<?php

namespace App\Controller;

use App\Entity\Organization;
use App\Exceptions\SecurityException;
use App\Service\LogEvents;
use Psr\Log\LoggerInterface;
use App\Service\Request\ParametersValidator;
use App\Service\Request\RequestParameters;
use App\Service\Security\RequestSecurity;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\ConstraintViolationListInterface;

/**
 * Class CommonController
 * Manages common methods for all other controllers that must inherit them
 * Supports services whose logging, sending queries, capturing errors and determining responses to return.
 * @package App\Controller
 * @author Thierry FAUCONNIER <th.fauconnier@outlook.fr>
 */
class CommonController extends AbstractController
{
    /**
     * Services
     */
    protected RequestSecurity $requestSecurity;
    protected RequestParameters $requestParameters;
    protected EntityManagerInterface $entityManager;
    protected ParametersValidator $paramValidator;
    private LogEvents $logEvents;
    private LoggerInterface $logger;

    /**
     * @var Response|null
     * Methods that can create a response should store it here and return a boolean to indicate the existence of the response.
     */
    protected ?Response $response;

    /**
     * @var Request
     * The request a secure faith of XSS risks must be stored here.
     */
    protected Request $request;

    /**
     * @var array
     * The RequestParameters call must store the query parameters of any type in the dataRequest array. Queries will use this data to define their sending
     */
    protected array $dataRequest;

    /**
     * @var array
     * The data of the sent requests are stored in this table. Each new data will overwrite the previous one. Methods that return data, must return a booleen indicating their existence here.
     */
    protected array $dataResponse;

    /**
     * @var array
     * Storage of dynamic Event message construction for LogEvent Service
     */
    protected array $eventInfo;

    /**
     * CommonController constructor.
     * @param RequestSecurity $requestSecurity
     * @param RequestParameters $requestParameters
     * @param EntityManagerInterface $entityManager
     * @param ParametersValidator $paramValidator
     * @param LoggerInterface $logger
     * @param LogEvents $logEvents
     */
    public function __construct(RequestSecurity $requestSecurity, RequestParameters $requestParameters, EntityManagerInterface $entityManager, ParametersValidator $paramValidator, LoggerInterface $logger, LogEvents $logEvents){
            $this->requestSecurity = $requestSecurity;
            $this->requestParameters = $requestParameters;
            $this->entityManager = $entityManager;
            $this->paramValidator = $paramValidator;
            $this->logger = $logger;
            $this->logEvents = $logEvents;
        }

    /**
     * @param Request $request
     * @return bool
     */
    public function cleanXSS(Request $request) :bool
    {
        //cleanXSS
        try {
            $this->request = $this->requestSecurity->cleanXSS($request);
        } catch (SecurityException $e) {
            $this->logger->warning($e);
            $this->response = new Response(
                json_encode(["error" => "ACCESS_FORBIDDEN"]),
                Response::HTTP_FORBIDDEN,
                ["Content-Type" => "application/json"]);
        }
        return isset($this->response);
    }

    /**
     * @param $requiredFields
     * @param $optionalFields
     * @param $className
     * @return bool
     */
    public function checkViolations($requiredFields, $optionalFields, $className) :bool
    {
        $this->paramValidator->initValidator($requiredFields, $optionalFields, $className, $this->dataRequest);
        try{
            $violationsList = $this->paramValidator->getViolations();

            if( count($violationsList) > 0 ){
                $this->response = new Response(
                    json_encode(["error" => $violationsList]),
                    Response::HTTP_BAD_REQUEST,
                    ["Content-Type" => "application/json"]
                );
            }
        }catch(Exception $e){
            $this->serverErrorResponse($e, "");
        }
        return isset($this->response);
    }

    //todo add context?
    /**
     * @param $entities
     * @return mixed
     */
    public function serialize($entities){
        foreach($entities as $key => $entity){
            $entities[$key] = $entity->serialize();
        }
        return $entities;
    }

    //todo not really useful? check it
    /**
     * @param $fields
     * @param $className
     * @return mixed
     */
    public function makeNewEntity($fields, $className) {
        return $this->setEntity(new $className(), $fields);
    }

    /**
     * @param $entity
     * @param $fields
     * @return mixed
     */
    public function setEntity($entity, $fields) {
        foreach($fields as $field){
            if(isset($this->dataRequest[$field])){
                $setter = 'set'.ucfirst($field);
                $entity->$setter($this->dataRequest[$field]);
            }
        }
        $this->isValid($entity, $fields);
        return $entity;
    }

    /**
     * @param $entity
     * @param $fields
     */
    public function isValid($entity, $fields) :void{
        $violationsList = $this->paramValidator->validObject($entity, $fields);
        if(count($violationsList) > 0 ){
            $vList =[];
            foreach($violationsList as $violations){
                foreach($violations as $v){
                    $this->logger->info($v);
                    $vList = array_merge(
                        $vList,
                        [$v->getPropertyPath() => $v->getMessage()]
                    );
                }
            }
            $this->response =  new Response(
                json_encode(["data" => $vList]),
                Response::HTTP_BAD_REQUEST,
                ["content-type" => "application/json"]
            );
        }
    }

    /**
     * @param $entity
     * @return bool
     */
    public function persistEntity($entity) :bool
    {
        $logInfo = "POST | " . get_class($entity);
        //persist the new entity
        try{
            $this->entityManager->persist($entity);
            $this->entityManager->flush();
            $this->dataResponse = [$entity];

            $this->eventInfo =["type" => $this->getClassName($entity), "desc" => "new registration"];
            $this->logger->info( $logInfo . "| REGISTRATION_SUCCESS | new id: " .$entity->getId());

        }catch(Exception $e){
            $this->serverErrorResponse($e, $logInfo);
        }
        return isset($this->response);
    }

    /**
     * @param $entity
     * @return bool
     */
    public function updateEntity($entity) :bool
    {
        $logInfo = "PUT | ". get_class($entity) . " | " .$entity->getId();
        try{
            $this->entityManager->flush();
            $this->eventInfo =["type" => $this->getClassName($entity), "desc" => "update"];
            $this->logger->info($logInfo . "| UPDATE_SUCCESS");
        }catch(Exception $e){
            $this->serverErrorResponse($e, $logInfo);
        }
        return isset($this->response);
    }

    /**
     * @param String $className
     * @param array $criterias
     * @return bool
     */
    public function getEntities(String $className, array $criterias) :bool {
        //initLog
        $logInfo = 'GET | ' .  $className;

        $repository = $this->entityManager->getRepository($className);
        try{
            //verifies the existence of criteria for query
            if(count($this->dataRequest) > 0 ) {
                if($this->hasAllCriteria($criterias)){
                    //initLog
                    foreach ($criterias as $key => $criteria) {
                        $logInfo .= " | by " . $criteria . " : " . $this->dataRequest[$criteria];
                        $criterias[$criteria] = $this->dataRequest[$criteria];
                        unset($criterias[$key]);
                    }
                    $this->dataResponse = $repository->findBy($criterias);
                }
            }else { //otherwise we return all users
                //initLog
                $logInfo .= " | ALL";
                $this->dataResponse = $repository->findAll();
            }
        }
        catch(Exception $e){
            $this->serverErrorResponse($e, $logInfo);
        }

        if(empty($this->dataResponse)){
            $logInfo .= " | DATA_NOT_FOUND";
            $this->response = $this->notFoundResponse();
        }
        else {$logInfo .= " | GET_SUCCESS | " . count($this->dataResponse) . " DATA_FOUND";}

        $this->logger->info($logInfo);
        return isset($this->response);
    }

    /**
     * @param array $criterias
     * @return bool
     */
    public function hasAllCriteria(array $criterias) :bool
    {
        foreach($criterias as $criteria){
            if(!isset($this->dataRequest[$criteria])){
                $this->logger->info(Response::HTTP_NOT_FOUND . " | missing parameter: " . $criteria);
                $this->response =  new Response(
                    json_encode(["error" => "missing parameter : " . $criteria . " is required "]),
                    Response::HTTP_BAD_REQUEST,
                    ["Content-Type" => "application/json"]
                );
                return false;
            }
        }
        return true;
    }

    /**
     * @return Response
     */
    public function successResponse() : Response {
        if(isset($this->eventInfo)){
            //handle case when userInterface isn't used (register)
            !$this->getUser() ? $user = $this->dataResponse[0] : $user = $this->getUser();

            $this->logEvents->addEvents($user, $this->dataResponse[0]->getId(), $this->eventInfo["type"], $this->eventInfo["desc"]);
        }
        if(empty($this->dataResponse)){
             return $this->notFoundResponse();
        }else {
            return $this->response =  new Response(
                json_encode($this->serialize($this->dataResponse)),
                Response::HTTP_OK,
                ["content-type" => "application/json"]
            );
        }
    }

    /**
     * @return Response
     */
    public function notFoundResponse() :Response{
        return  $this->response =  new Response(
            json_encode(["data" => "DATA_NOT_FOUND"]),
            Response::HTTP_OK,
            ["content-type" => "application/json"]
        );
    }

    /**
     * @param Exception $e
     * @param String $logInfo
     */
    public function serverErrorResponse(Exception $e, String $logInfo) :void
    {
        $logInfo .= $logInfo . " | FAILED | ";
        $this->logger->log("error",$logInfo . $e);

        $this->response = new Response(
            json_encode(["error" => "ERROR_SERVER"]),
            Response::HTTP_INTERNAL_SERVER_ERROR,
            ["Content-Type" => "application/json"]
        );
    }

    /**
     * @param $entity
     * @return String
     */
    public function getClassName($entity) :String {
        $namespace = explode("/", get_class($entity));
        return end($namespace);
    }
}
