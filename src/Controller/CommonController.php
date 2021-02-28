<?php

namespace App\Controller;

use App\Entity\ActivityFile;
use App\Exceptions\SecurityException;
use App\Exceptions\ViolationException;
use App\Service\LogService;
use App\Service\FileHandler;
use App\Service\Request\ParametersValidator;
use App\Service\Request\RequestParameters;
use App\Service\Security\RequestSecurity;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\Exception\NoFileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\HeaderUtils;
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
    protected FileHandler $fileHandler;
    protected EntityManagerInterface $entityManager;
    protected ParametersValidator $paramValidator;

    //todo dispatch in LoggerService
    private $logInfo = "";

    //todo maybe add context here?

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
     * Logger service to trace error and user actions
     * @var LogService
     */
    protected LogService $logService;

    /**
     * CommonController constructor.
     * @param RequestSecurity $requestSecurity
     * @param RequestParameters $requestParameters
     * @param EntityManagerInterface $entityManager
     * @param ParametersValidator $paramValidator
     * @param FileHandler $picHandler
     * @param LogService $logService
     */
    public function __construct(RequestSecurity $requestSecurity, RequestParameters $requestParameters, EntityManagerInterface $entityManager, ParametersValidator $paramValidator, FileHandler $picHandler, LogService $logService){
            $this->requestSecurity = $requestSecurity;
            $this->requestParameters = $requestParameters;
            $this->fileHandler = $picHandler;
            $this->entityManager = $entityManager;
            $this->paramValidator = $paramValidator;
            $this->logService = $logService;
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

            //todo test log
            $this->logService->logError($e,$this->getUser(), "WARNING");

            $this->response = new Response(
                json_encode(["error" => "ACCESS_FORBIDDEN"]),
                Response::HTTP_FORBIDDEN,
                ["Content-Type" => "application/json"]);
        }
        return isset($this->response);
    }



    //todo just trhow?
    /**
     * @param $requiredFields
     * @param $optionalFields
     * @param $className
     * @return bool
     */
    public function isInvalid($requiredFields, $optionalFields, $className) :bool
    {
        $this->paramValidator->initValidator($requiredFields, $optionalFields, $className, $this->dataRequest);
        try{
            $this->paramValidator->getViolations();

        }catch(ViolationException $e){
            $this->logService->logError($e, $this->getUser(), "ERROR");

            $this->BadRequestResponse($e->getViolationsList());
        }
        return isset($this->response);
    }




    /**
     * @param $entities
     * @param String|null $context
     * @return mixed
     */
    public function serialize($entities, String $context = null){
        foreach($entities as $key => $entity){
            if(gettype($entity) != "string"){
                $entities[$key] = $entity->serialize($context);
            }
        }
        return $entities;
    }




    /**
     * @param $entity
     * @param $fields
     * @return mixed
     */
    public function setEntity($entity, $fields)
    {
            foreach($fields as $field){
                if(isset($this->dataRequest[$field])) {
                    $setter = 'set'.ucfirst($field);
                    $entity->$setter($this->dataRequest[$field]);
                }
            }
        return $entity;
    }




    /**
     * @param $entity
     * @return bool
     */
    public function persistEntity($entity) :bool
    {
        try{
            $this->entityManager->persist($entity);
            $this->entityManager->flush();
            $this->dataResponse = [$entity];

        }catch(Exception $e){
            $this->logService->logError($e,$this->getUser(),"error");
            $this->serverErrorResponse($e, "An error occured");
        }

        $this->logService->logInfo($this->getClassName($entity) ." ". $this->dataResponse[0]->getid() ." was created. " );

        $this->logService->logEvent($this->getUser(), $this->dataResponse[0]->getId(), $this->getClassName($entity), "new Registration");

        return isset($this->response);
    }





    /**
     * @param $entity
     * @return bool
     */
    public function deleteEntity($entity) :bool
    {
        $id = $entity->getId();
        $classname = $this->getClassName($entity);
        try{
            $this->entityManager->remove($entity);
            $this->entityManager->flush();
            $this->dataResponse = ["success"];

        }catch(Exception $e){
            $this->logService->logError($e,$this->getUser(),"error");
            $this->serverErrorResponse($e, "An error occured");
        }

        $this->logService->logInfo($classname ." ". $id ." was deleted. " );

        $this->logService->logEvent($this->getUser(),$id, $classname, "Delete");

        return isset($this->response);
    }






    /**
     * @param $entity
     * @return bool
     */
    public function updateEntity($entity) :bool
    {
        try{
            $this->entityManager->flush();
            $this->dataResponse = [$entity];
            $this->eventInfo =["type" => $this->getClassName($entity), "desc" => "update"];

        }catch(Exception $e){
            $this->logService->logError($e,$this->getUser(),"error");
            $this->serverErrorResponse($e, "An error occured");
        }

        $this->logService->logInfo($this->getClassName($entity) ." ". $entity->getid() ." was updated. " );

        $this->logService->logEvent($this->getUser(),$entity->getId(), $this->getClassName($entity), "Update");

        return isset($this->response);
    }





    /**
     * @param String $className
     * @param array $criterias
     * @return bool
     */
    public function getEntities(String $className, array $criterias) :bool {

        $repository = $this->entityManager->getRepository($className);

        try{
            //if criterias for query
            $strLog = "all";
            if(count($this->dataRequest) > 0 ) {
                //verify the existence of criterias and their validity
                //todo throw vilotionException ?
                if($this->hasAllCriteria($criterias) && !($this->isInvalid($criterias, null, $className))){
                    $strLog = "[";
                    foreach ($criterias as $key => $criteria) {
                        $strLog .= $criteria .": ". $this->dataRequest[$criteria]."; ";
                        $criterias[$criteria] = $this->dataRequest[$criteria];
                        unset($criterias[$key]);
                    }
                    $strLog .= "]";
                    $this->dataResponse = $repository->findBy($criterias);
                }
            }else { //otherwise return all entities
                $this->dataResponse = $repository->findAll();
            }
        }
        catch(Exception $e){
            $this->logService->logError($e,$this->getUser(),"error" );
            $this->serverErrorResponse($e, $this->logInfo);
        }

        $this->logService->logInfo($className ." by ". $strLog ." read. " );

        return isset($this->response);
    }






//todo logg for pics
    /**
     * @param $entity
     * @return mixed
     */
    public function loadPicture($entity) {
        $className = $this->getClassName($entity);
        if($className === "ActivityFile"){ $className = "Activity";}
        $fileDir = '/pictures/'.$className.'/'.$entity->getPicturePath();

        if($entity->getPicturePath() !== null){
            try {
                $img = $this->fileHandler->getPic($fileDir);
                $entity->setPictureFile($img);

            }catch(Exception $e){
                $this->logService->logError($e, $this->getUser(), "error");
                $this->serverErrorResponse($e, $this->logInfo);

            }
        }
        $this->logService->logInfo($className ." id ". $entity->getId() ." load Picture " );
        return $entity;
    }




    /**
     * @param $entity
     * @param UploadedFile $file
     * @return bool
     */
    public function uploadPicture($entity, UploadedFile $file){

        $className = $this->getClassName($entity);
        if($className === "ActivityFile"){$className = "Activity";}
        $fileDir = '/pictures/'.$className;

        try{
            $oldPic = null;
            if($entity->getPicturePath() !== null){
                $oldPic =$entity->getPicturePath();
            }

            //make unique picturePath
            $entity->setPicturePath(uniqid().'_'.$this->fileHandler->getOriginalFilename($file).'.'.$file->guessExtension());

            $this->fileHandler->upload($fileDir, $entity->getPicturePath(), $file);
            $this->dataRequest["picturePath"] = $entity->getPicturePath();

            //if a picture already exist, need to remove it
            if($oldPic !== null){
                $this->logService->logInfo($className ." id " . $entity->getId() . " remove old Picture FAILED " );
                $this->fileHandler->removeFile($fileDir.'/'.$oldPic);
            }
        }catch(Exception $e){
            $this->logService->logError($e,$this->getUser(),"error");
            $this->serverErrorResponse($e, $this->logInfo);
        }

        $this->logService->logInfo($className ." id " . $entity->getId() . " uploaded Picture " );

        return isset($this->response);
    }






    /**
     * @param ActivityFile $activityFile
     * @param UploadedFile $file
     * @return bool|Response
     */
    public function uploadFile(ActivityFile $activityFile, UploadedFile $file) {


        //check if Mime is allowed
        try{
            $this->fileHandler->isAllowedMime($file);
        }catch (Exception $e){
            $this->logService->logError($e,$this->getUser(),"error" );
            return $this->BadMediaResponse($e->getMessage());
        }

        try{
            //setter
            $activityFile->setFilename($this->fileHandler->getOriginalFilename($file).".".$file->guessExtension());
            $activityFile->setFileType($file->getMimeType());
            $activityFile->setSize($file->getSize());
            $activityFile->setUniqId(uniqid());

            //make new filename
            $completName = $activityFile->getUniqId(). '_'. $activityFile->getFilename();

            //upload
            $this->fileHandler->upload('/files/Activity', $completName, $file);

            //make new checksum
            $activityFile->setChecksum($this->fileHandler->getChecksum('/files/Activity/'. $completName));

            $this->dataResponse = [$activityFile];

        }catch(Exception $e){
            $this->logService->logError($e, $this->getUser(),"error");
            $this->logService->logEvent($this->getUser(),$activityFile->getId(), $this->getClassName($activityFile), "SERVER_ERROR : upload_File FAILED");
            $this->serverErrorResponse($e, "An error occurred");
        }

        return isset($this->response);
    }






    /**
     * @param ActivityFile $activityFile
     * @return mixed
     */
    public function getFile(ActivityFile $activityFile) : bool {

        $className = $this->getClassName($activityFile);

        //todo
        if($activityFile->getFilename() !== null && $activityFile->getUniqId() !== null )
        {
            $path = '/files/Activity/'.$activityFile->getUniqId(). '_'. $activityFile->getFilename();

            try {
                $this->fileHandler->controlChecksum($path, $activityFile->getChecksum());
            } catch(Exception $e) {
                $this->logService->logError($e, $this->getUser(),"error");
                return $this->CorruptResponse("Compromised file");
            }

            try {
                //todo dowload File
                $fileDir = $this->fileHandler->getFile($path);
           //     $response = new BinaryFileResponse($fileDir);
            //    $response->headers->set('Content-Type', $activityFile->getFileType());

                // load the file from the filesystem
                $this->dataResponse = [$this->fileHandler->getFile($path)];

                // rename the downloaded file
        //        $this->file($file, $activityFile->getFilename().".".$activityFile->getFileType());

                // display the file contents in the browser instead of downloading it
       //         $this->file('invoice_3241.pdf', 'my_invoice.pdf', ResponseHeaderBag::DISPOSITION_INLINE);

                // send the file contents and force the browser to download it
           //     $this->dataResponse = [$this->file($file)];
                /*
 *
                $disposition = HeaderUtils::makeDisposition(
                    HeaderUtils::DISPOSITION_ATTACHMENT,
                    $activityFile->getFilename().".".$activityFile->getFileType()
                );
                $response->headers->set('Content-Disposition', $disposition);

                $this->dataResponse = [$response];*/
          //      stream_($this->fileHandler->getFile($className, $activityFile->getFilePath()));

                //complet path
              //  $this->dataResponse = [$this->fileHandler->getFile($path)];
             //   $activityFile->setFile($file);
            }catch(Exception $e){
                $this->logService->logError($e,$this->getUser(),"warning");
                $this->serverErrorResponse($e, $this->logInfo);
            }
        }
        $this->logService->logInfo("ActivityFile id " . $activityFile->getId() . " downloaded File " );

   //     dd($this->getUser());
        $this->logService->logEvent($this->getUser(),$activityFile->getId(), $this->getClassName($activityFile), "download file");

        return isset($this->response);
    }





    /**
     * @param String $className
     * @param String $attributeName
     * @param String $idKey
     * @return bool
     */
    public function getLinkedEntity(String $className, String $attributeName, String $idKey) :bool {
        $this->dataRequest = array_merge($this->dataRequest, ["id" => $this->dataRequest[$idKey]]);
        if(!$this->getEntities($className, ["id"])){
            if(!empty($this->dataResponse)){
                $this->dataRequest[$attributeName] = $this->dataResponse[0];
                unset($this->dataRequest[$idKey]);
            }else {
                $this->logService->logInfo($className . " id :" . $this->dataRequest[$idKey] . " linked entity not found. " );
                $this->notFoundResponse();
            }
        }
        $this->logService->logInfo($className . " id :" . $this->dataRequest['id'] . " linked entity found. " );
        return isset($this->response);
    }






    //todo maybe problem with return response
    //todo just throw?
    /**
     * @param array $criterias
     * @return bool
     */
    public function hasAllCriteria(array $criterias) :bool
    {
        try {
            $tabMissing = [];
            foreach($criterias as $criteria){
                if(!isset($this->dataRequest[$criteria])){
                    $tabMissing[] = "missing parameter : " . $criteria . " is required. ";
                }
            }
            if(count($tabMissing) > 0 ) {
                throw new ViolationException($tabMissing);
            }
        } catch (ViolationException $e) {
            $this->logService->logError($e,$this->getUser(),"error");
            $this->BadRequestResponse($tabMissing);
            return false;
        }

        return true;
    }







    /**
     * @param String|null $context
     * @return Response
     */
    public function successResponse(String $context = null) : Response {

        if(empty($this->dataResponse)){
             return $this->notFoundResponse();
        }else {
            $this->logService->logInfo('Request was successfully done. ');
            return $this->response =  new Response(
                json_encode(
                    $this->serialize($this->dataResponse, $context)
                ),
                Response::HTTP_OK,
                ["content-type" => "application/json"]
            );
        }
    }






    //todo retourne vide ou data non found? vide serait plus simple pou rle front...
    /**
     * @return Response
     */
    public function notFoundResponse() :Response{
        return  $this->response =  new Response(
            //todo stocker/ construire la chaine message log dans le service log
            //$logInfo .= " | DATA_NOT_FOUND";
            json_encode(["DATA_NOT_FOUND"]),
            Response::HTTP_OK,
            ["content-type" => "application/json"]
        );
    }






    public function BadRequestResponse(Array $violations) :Response{
        return  $this->response =  new Response(
            json_encode($violations),
            Response::HTTP_BAD_REQUEST,
            ["content-type" => "application/json"]
        );
    }






    public function BadMediaResponse($message) :Response{
        return  $this->response =  new Response(
            json_encode($message),
            Response::HTTP_UNSUPPORTED_MEDIA_TYPE,
            ["content-type" => "application/json"]
        );
    }






    public function CorruptResponse(String $message) :Response{
        return  $this->response =  new Response(
            json_encode($message),
            Response::HTTP_UNAUTHORIZED,
            ["content-type" => "application/json"]
        );
    }





    /**
     * @param Exception $e
     * @param String $logInfo
     */
    public function serverErrorResponse(Exception $e, String $logInfo) :void
    {
        //todo check message
        $this->response = new Response(
            json_encode($e->getMessage()),
            Response::HTTP_INTERNAL_SERVER_ERROR,
            ["Content-Type" => "application/json"]
        );
    }


    /**
     * @param $message
     * @return Response
     */
    public function unauthorizedResponse($message){
        return $this->response = new Response(
            json_encode($message),
            Response::HTTP_UNAUTHORIZED,
            ["Content-Type" => "application/json"]
        );
    }



    //todo really usefull?
    /**
     * @param $entity
     * @return String
     */
    public function getClassName($entity) :String {
        $namespace = explode("\\", get_class($entity));
        return end($namespace);
    }
}
