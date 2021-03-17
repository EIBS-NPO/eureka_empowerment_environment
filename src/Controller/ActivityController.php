<?php

namespace App\Controller;

use App\Entity\Activity;
use App\Entity\Project;
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
 * Class ActivityController
 * @package App\Controller
 * @Route("activity", name="activity")
 */
class ActivityController extends AbstractController
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
    public function create(Request $request): Response
    {
        try{$this->security->cleanXSS($request);}
        catch(SecurityException $e) {
            $this->logger->logError($e, $this->getUser(), "warning");
            return $this->responseHandler->forbidden();
        }

        // recover all data's request
        $this->parameters->setData($request);
        $this->parameters->addParam("creator", $this->getUser());
        $this->parameters->addParam("postDate", New \DateTime("now"));

        if(!$this->parameters->getData('isPublic')){
            $this->parameters->addParam("isPublic", false);
        }

        //check params Validations
        try{ $this->validator->isInvalid(
            ["title", "summary", "postDate", "creator"],
            [],
            Activity::class);
        } catch(ViolationException $e){
            $this->logger->logError($e, $this->getUser(), "error");
            return $this->responseHandler->BadRequestResponse($e->getViolationsList());
        }

        //create Activity object && set validated fields
        $activity = new Activity();
        foreach( ["title", "summary", "postDate", "creator"]
                 as $field ) {
            if($this->parameters->getData($field) !== false ) {
                $setter = 'set'.ucfirst($field);
                $activity->$setter($this->parameters->getData($field));
            }
        }

        //persist the new activity
        try{
            $this->entityManager->persist($activity);
            $this->entityManager->flush();
        }catch(Exception $e){
            $this->logger->logError($e,$this->getUser(),"error");
            return $this->responseHandler->serverErrorResponse($e, "An error occured");
        }

        //success response
        return $this->responseHandler->successResponse([$activity]);
    }





    /**
     * @param Request $request
     * @return Response
     * @throws Exception
     * @Route("", name="_put", methods="put")
     */
    public function updateActivity (Request $request) :Response
    {
        try{$request = $this->security->cleanXSS($request);}
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
            ["title", "summary", "isPublic"],
            Project::class);
        } catch(ViolationException $e){
            $this->logger->logError($e, $this->getUser(), "error");
            return $this->responseHandler->BadRequestResponse($e->getViolationsList());
        }

        //for no admin get org by user
        if($this->getUser()->getRoles()[0] !== "ROLE_ADMIN"){
            $repository = $this->entityManager->getRepository(User::class);
            $userData = $repository->findBy(["id" => $this->getUser()->getId()]);
            $user = $userData[0];

            $activityData = $user->getActivity($this->parameters->getData("id"));
        }
        else{//for admin
            $repository = $this->entityManager->getRepository(Activity::class);
            $activityData = $repository->findBy(["id" => $this->parameters->getData("id")]);
            if(count($activityData) === 0 ){
                $this->logger->logInfo(" Activity with id : ". $this->parameters->getData("id") ." not found " );
                return $this->responseHandler->notFoundResponse();
            }
            $activityData = $activityData[0];
        }

//only for referent or admin
        if($activityData !== false ){
            foreach( ["title", "summary", "isPublic"]
                     as $field ) {
                if($this->parameters->getData($field) !== false ) {
                    $setter = 'set'.ucfirst($field);
                    $activityData->$setter($this->parameters->getData($field));
                }
            }

            $this->entityManager->flush();
            if(gettype($activityData) !== "array"){
                $activityData = [$activityData];
            }
            //load picture
            foreach($activityData as $key => $activity){
                if($activity->getProject() !== null ){
                    $activity->setProject( $this->fileHandler->loadPicture($activity->getProject()));
                }
                if($activity->getOrganization() !== null ){
                    $activity->setOrganization( $this->fileHandler->loadPicture($activity->getOrganization()));
                }
                $activityData[$key] = $this->fileHandler->loadPicture($activity);
            }

        }else{
            $this->responseHandler->unauthorizedResponse("unauthorized");
        }

        //success response
        return $this->responseHandler->successResponse($activityData, "read_activity");
    }




    /**
     * @param Request $request
     * @return Response
     * @Route("/picture", name="_picture_put", methods="post")
     */
    public function putPicture(Request $request ) :Response {

        try{$request = $this->security->cleanXSS($request);}
        catch(SecurityException $e) {
            $this->logger->logError($e, $this->getUser(), "warning");
            return $this->responseHandler->forbidden();
        }

        // recover all data's request
        $this->parameters->setData($request);

        //check if required params exist
        try{ $this->parameters->hasData(["id", "image"]); }
        catch(ViolationException $e) {
            $this->logger->logError($e, $this->getUser(), "error");
            return $this->responseHandler->BadRequestResponse($e->getViolationsList());
        }

        try{
            //for no admin get org by user
            if($this->getUser()->getRoles()[0] !== "ROLE_ADMIN"){
                $repository = $this->entityManager->getRepository(User::class);
                $userData = $repository->findBy(["id" => $this->getUser()->getId()]);
                $user = $userData[0];

                $activityData = $user->getActivity($this->parameters->getData("id"));
            }
            else{//for admin
                $repository = $this->entityManager->getRepository(Activity::class);
                $activityData = $repository->findBy(["id" => $this->parameters->getData("id")]);
                if(count($activityData) === 0 ){
                    $this->logger->logInfo(" Activity with id : ". $this->parameters->getData("id") ." not found " );
                    return $this->responseHandler->notFoundResponse();
                }
                $activityData = $activityData[0];
            }

            if($activityData !== false ){
                $oldPic = $activityData->getPicturePath() ? $activityData->getPicturePath() : null;

                $fileDir = '/pictures/Activity';
                $picFile = $this->parameters->getData("image");

                //make unique picturePath
                $activityData->setPicturePath(uniqid().'_'. $this->fileHandler->getOriginalFilename($picFile).'.'. $picFile->guessExtension());

                //upload
                $this->fileHandler->upload($fileDir, $activityData->getPicturePath(), $picFile);

                $this->entityManager->flush();
                $activityData = $this->fileHandler->loadPicture($activityData);

                //if a picture already exist, need to remove it
                if($oldPic !== null){
                    $this->logger->logInfo(" User with id " . $this->getUser()->getId() . " remove old Picture for Activity with id ". $activityData->getId() );
                    $this->fileHandler->removeFile($fileDir.'/'.$oldPic);
                }

            }else{
                $this->responseHandler->unauthorizedResponse("unauthorized");
            }

        }catch(Exception $e){
            $this->logger->logError($e,$this->getUser(),"error" );
            return $this->responseHandler->serverErrorResponse($e, "An error occured ");
        }

        //final response
        return $this->responseHandler->successResponse([$activityData], "read_activity");
    }

//todo delete picture


    /**
     * returns all public activities
     * @Route("/public", name="_get_public", methods="get")
     * @param Request $request
     * @return Response
     */
    public function getPublicActivities(Request $request): Response {
        try{$this->security->cleanXSS($request);}
        catch(SecurityException $e) {
            $this->logger->logError($e, $this->getUser(), "warning");
            return $this->responseHandler->forbidden();
        }

        // recover all data's request
        $this->parameters->setData($request);

        $criterias = [];
        if($this->parameters->getData('id') !== false){
            $criterias["id"]= $this->parameters->getData('id') ;
        }
        $criterias["isPublic"] = true;

        $repository = $this->entityManager->getRepository(Activity::class);
        //get query, if id not define, query getALL
        try{
            $dataResponse = $repository->findBy($criterias);
//            dd($dataResponse[0]->getOrganization());
        }catch(Exception $e){
            $this->logger->logError($e,$this->getUser(),"error" );
            return $this->responseHandler->serverErrorResponse($e, "An error occured");
        }

        //load picture
        foreach($dataResponse as $key => $activity){
            if($activity->getProject() !== null ){
                $activity->setProject( $this->fileHandler->loadPicture($activity->getProject()));
            }
            if($activity->getOrganization() !== null ){
                $activity->setOrganization( $this->fileHandler->loadPicture($activity->getOrganization()));
            }
            $dataResponse[$key] = $this->fileHandler->loadPicture($activity);
        }

        return $this->responseHandler->successResponse($dataResponse, "read_activity");
    }






    /**
     * returns to a user his created projects
     * @Route("", name="_get", methods="get")
     * @param Request $request
     * @return Response
     */
    public function getActivities(Request $request): Response
    {
        try {
            $this->security->cleanXSS($request);
        } catch (SecurityException $e) {
            $this->logger->logError($e, $this->getUser(), "warning");
            return $this->responseHandler->forbidden();
        }

        // recover all data's request
        $this->parameters->setData($request);


        $criterias = [];
        if ($this->parameters->getData('id') !== false) {
            $criterias["id"] = $this->parameters->getData('id');
        }
        if( $this->parameters->getData("creator") !== false){
            $criterias["creator"] = $this->getUser()->getId();
        }

        $repository = $this->entityManager->getRepository(Activity::class);
        //get query, if id not define, query getALL
        try {
            $dataResponse = $repository->findBy($criterias);
        } catch (Exception $e) {
            $this->logger->logError($e, $this->getUser(), "error");
            return $this->responseHandler->serverErrorResponse($e, "An error occured");
        }

        $repository = $this->entityManager->getRepository(User::class);
        $criterias["id"] = $this->getUser()->getId();
        try {
            $userData = $repository->findBy($criterias);
        } catch (Exception $e) {
            $this->logger->logError($e, $this->getUser(), "error");
            return $this->responseHandler->serverErrorResponse($e, "An error occured");
        }
        $user = $userData[0];

        //check if public or private data return
        $tab=[];
        foreach ($dataResponse as $activity) {
            if ($activity->hasAccess($user)) {
                $tab[] = $activity;
            }
        }
        $dataResponse = $tab;

        //load picture
        foreach ($dataResponse as $key => $activity) {
            if ($activity->getProject() !== null) {
                $activity->setProject($this->fileHandler->loadPicture($activity->getProject()));
            }
            if ($activity->getOrganization() !== null) {
                $activity->setOrganization($this->fileHandler->loadPicture($activity->getOrganization()));
            }
            $dataResponse[$key] = $this->fileHandler->loadPicture($activity);
        }

        //success response
        return $this->responseHandler->successResponse($dataResponse, "read_activity");
    }

    /**
     * @param Request $request
     * @return Response
     * @Route("", name="_delete", methods="delete")
     */
    public function remove(Request $request) : Response {
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

        try {
            //for no admin get org by user
            if ($this->getUser()->getRoles()[0] !== "ROLE_ADMIN") {
                $repository = $this->entityManager->getRepository(User::class);
                $userData = $repository->findBy(["id" => $this->getUser()->getId()]);
                $user = $userData[0];

                $activityData = $user->getActivity($this->parameters->getData("id"));
            } else {//for admin
                $repository = $this->entityManager->getRepository(Activity::class);
                $activityData = $repository->findBy(["id" => $this->parameters->getData("id")]);
                if (count($activityData) === 0) {
                    $this->logger->logInfo(" Activity with id : " . $this->parameters->getData("id") . " not found ");
                    return $this->responseHandler->notFoundResponse();
                }
                $activityData = $activityData[0];
            }

            $this->entityManager->remove($activityData);
            $this->entityManager->flush();

            return $this->responseHandler->successResponse(["success"]);

        }catch(Exception $e){
            $this->logger->logError($e,$this->getUser(),"error" );
            return $this->responseHandler->serverErrorResponse($e, "An error occured");
        }
    }

    /*public function getPics($activities){
        //download picture
        foreach($activities as $key => $activity){
            $activities[$key] = $this->loadPicture($activity);
            if($activity->getProject() !== null){
                $activity->setProject($this->loadPicture($activity->getProject()));
            }
            if($activity->getOrganization() !== null){
                $activity->setOrganization($this->loadPicture($activity->getOrganization()));
            }
        }
        return $activities;
    }*/
}
