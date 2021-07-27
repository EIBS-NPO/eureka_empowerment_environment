<?php

namespace App\Controller;

use App\Entity\Activity;
use App\Entity\Organization;
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

//todo remove requestSecurity dependance
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

    /**
     * OrgController constructor.
     * @param RequestParameters $requestParameters
     * @param ResponseHandler $responseHandler
     * @param ParametersValidator $validator
     * @param EntityManagerInterface $entityManager
     * @param FileHandler $fileHandler
     * @param LogService $logger
     */
    public function __construct(RequestParameters $requestParameters, ResponseHandler $responseHandler, ParametersValidator $validator, EntityManagerInterface $entityManager, FileHandler $fileHandler, LogService $logger)
    {
        $this->parameters = $requestParameters;
        $this->responseHandler = $responseHandler;
        $this->validator = $validator;
        $this->entityManager = $entityManager;
        $this->fileHandler = $fileHandler;
        $this->logger = $logger;
    }


    /**
     * @Route("", name="_registration", methods="post")
     * @param Request $request
     * @return Response
     */
    public function create(Request $request) :Response
    {
        // recover all data's request
        $this->parameters->setData($request);
        $this->parameters->addParam("referent", $this->getUser());

        //check params Validations
        try{ $this->validator->isInvalid(
            ["name", "type", "email", "referent"],
            ["phone", 'description'],
            Organization::class);
        } catch(ViolationException $e){
            $this->logger->logError($e, $this->getUser(), "error");
            return $this->responseHandler->BadRequestResponse($e->getViolationsList());
        }

        //create org object && set validated fields
        $org = new Organization();
        foreach( ["name", "type", "email", "referent", "phone", 'description']
                 as $field ) {
            if($this->parameters->getData($field) !== false ) {
                $setter = 'set'.ucfirst($field);
                $org->$setter($this->parameters->getData($field));
            }
        }

        //persist the new org
        try{
            $this->entityManager->persist($org);
            $this->entityManager->flush();
        }catch(Exception $e){
            $this->logger->logError($e,$this->getUser(),"error");
            return $this->responseHandler->serverErrorResponse($e, "An error occured");
        }

        //success response
        return $this->responseHandler->successResponse([$org], "read_project");
    }


    /**
     * @Route("/public", name="_get_public", methods="get")
     * @param Request $request
     * @return Response
     */
    public function getPublicOrg(Request $request) :Response
    {
        // recover all data's request
        $this->parameters->setData($request);

        $criterias = [];
        if($this->parameters->getData('id') !== false){
            $criterias["id"]= $this->parameters->getData('id') ;
        }
        if($this->parameters->getData('isPartner') !== false){
            $criterias["isPartner"]= true;
        }

        $repository = $this->entityManager->getRepository(Organization::class);
        //get query, if id not define, query getALL
        try{
            if(count($criterias) > 0 ){
                $dataResponse = $repository->findBy($criterias);
            }else {
                $dataResponse = $repository->findAll();
            }
        }catch(Exception $e){
            $this->logger->logError($e,$this->getUser(),"error" );
            return $this->responseHandler->serverErrorResponse($e, "An error occured");
        }

        //only with public resources
        foreach($dataResponse as $org) {
            $org->setActivities($org->getOnlyPublicActivities());
        }

        //download picture
        foreach($dataResponse as $key => $org){
            $org= $this->fileHandler->loadPicture($org);
            foreach($org->getActivities() as $activity){
                $activity = $this->fileHandler->loadPicture($activity);
            }
            foreach($org->getProjects() as $project){
                $project = $this->fileHandler->loadPicture($project);
            }
            foreach($org->getMembership() as $member){
                $member = $this->fileHandler->loadPicture($member);
            }
            $dataResponse[$key] = $org;
        }

        //success response
        return $this->responseHandler->successResponse($dataResponse, "read_org");
    }

//    /**
//     * @Route("/partner", name="_get_partner", methods="get")
//     * @param Request $request
//     * @return Response
//     */
//    public function getPartnerOrg(Request $request) :Response {
//        // recover all data's request
//        $this->parameters->setData($request);
//
//        $criterias = ["isPartner" => true];
//        $repository = $this->entityManager->getRepository(Organization::class);
//        //get query, if id not define, query getALL
//        try{
//            $dataResponse = $repository->findBy($criterias);
//        }catch(Exception $e){
//        $this->logger->logError($e,$this->getUser(),"error" );
//        return $this->responseHandler->serverErrorResponse($e, "An error occured");
//        }
//
//        //success response
//        return $this->responseHandler->successResponse($dataResponse, "read_org");
//    }

    /**
     * @Route("", name="_get", methods="get")
     * @param Request $request
     * @return Response|null
     */
    public function getOrg(Request $request){
        // recover all data's request
        $this->parameters->setData($request);

        $criterias = [];
        if($this->parameters->getData('id') !== false){
            $criterias["id"]= $this->parameters->getData('id') ;
        }

        $repository = $this->entityManager->getRepository(Organization::class);
        //get query, if id not define, query getALL
        try{
            if(count($criterias) > 0){
                $dataResponse = $repository->findBy($criterias);
            }else {
                $dataResponse = $repository->findAll();
            }
        }catch(Exception $e){
            $this->logger->logError($e,$this->getUser(),"error" );
            return $this->responseHandler->serverErrorResponse($e, "An error occured");
        }

        $repository = $this->entityManager->getRepository(User::class);
        $criterias["id"] = $this->getUser()->getId() ;
        try {
            $userData = $repository->findBy($criterias);
        }catch(Exception $e){
            $this->logger->logError($e,$this->getUser(),"error" );
            return $this->responseHandler->serverErrorResponse($e, "An error occured");
        }
        $user = $userData[0];

        //check if public or private data return
        foreach($dataResponse as $org){
            if(!$org->isMember($user)){
                $org->setActivities($org->getOnlyPublicActivities());
            }
        }

        //download picture
        foreach($dataResponse as $key => $org){
            $org= $this->fileHandler->loadPicture($org);
            foreach($org->getActivities() as $activity){
                $activity = $this->fileHandler->loadPicture($activity);
            }
            foreach($org->getProjects() as $project){
                $project = $this->fileHandler->loadPicture($project);
            }
            foreach($org->getMembership() as $member){
                $member = $this->fileHandler->loadPicture($member);
            }
            $dataResponse[$key] = $org;
        }

        //success response
        return $this->responseHandler->successResponse($dataResponse, "read_org");
    }

    //todo ajout logo update

    /**
     * @param Request $request
     * @return Response
     * @ROUTE("", name="_put", methods="put")
     */
    public function updateOrganization(Request $request) :Response
    {
        // recover all data's request
        $this->parameters->setData($request);

        //check if required params exist
        try{ $this->parameters->hasData(["orgId"]); }
        catch(ViolationException $e) {
            $this->logger->logError($e, $this->getUser(), "error");
            return $this->responseHandler->BadRequestResponse($e->getViolationsList());
        }

        //check params Validations
        try{ $this->validator->isInvalid(
            [],
            ["name", "type", "email", "phone", 'description', "isPartner"],
            Organization::class);
        } catch(ViolationException $e){
            $this->logger->logError($e, $this->getUser(), "error");
            return $this->responseHandler->BadRequestResponse($e->getViolationsList());
        }

        //for no admin get org by user
        if($this->getUser()->getRoles()[0] !== "ROLE_ADMIN"){
            $repository = $this->entityManager->getRepository(User::class);
            $userData = $repository->findBy(["id" => $this->getUser()->getId()]);
            $user = $userData[0];

            $orgData = $user->getOrgById($this->parameters->getData("orgId"));
        }
        else{//for admin
            $repository = $this->entityManager->getRepository(Organization::class);
            $orgData = $repository->findBy(["id" => $this->parameters->getData("orgId")]);
            if(count($orgData) === 0 ){
                $this->logger->logInfo(" Organization with id : ". $this->parameters->getData("orgId") ." not found " );
                return $this->responseHandler->notFoundResponse();
            }
            $orgData = $orgData[0];
        }

        //only for referent or admin
        if($orgData !== false ){
            foreach( ["name", "type", "email", "phone", 'description', "isPartner"] as $field ) {
                if(isset($this->parameters->getAllData()[$field]) ) {
                    if($field !=="isPartner" || ($field === "isPartner" && $this->getUser()->getRoles()[0] == "ROLE_ADMIN")){
                        $setter = 'set'.ucfirst($field);
                        $orgData->$setter($this->parameters->getData($field));
                    }
                }
            }

            $this->entityManager->flush();
            $orgData = $this->fileHandler->loadPicture($orgData);

        }else{
            $this->responseHandler->unauthorizedResponse("unauthorized");
        }

        //final response
        return $this->responseHandler->successResponse([$orgData], "read_org");
    }

    /**
     * @param Request $request
     * @return Response
     * @Route("/picture", name="_picture_put", methods="post")
     */
    public function putPicture(Request $request ) :Response {

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

                $orgData = $user->getOrgById($this->parameters->getData("id"));
            }
            else{//for admin
                $repository = $this->entityManager->getRepository(Organization::class);
                $orgData = $repository->findBy(["id" => $this->parameters->getData("id")]);
                if(count($orgData) === 0 ){
                    $this->logger->logInfo(" Organization with id : ". $this->parameters->getData("id") ." not found " );
                    return $this->responseHandler->notFoundResponse();
                }
                $orgData = $orgData[0];
            }

            if($orgData !== false ){

                $oldPic = $orgData->getPicturePath() ? $orgData->getPicturePath() : null;

                $fileDir = '/pictures/Organization';
                $picFile = $this->parameters->getData("image");

                //make unique picturePath
                $orgData->setPicturePath(uniqid().'_'. $this->fileHandler->getOriginalFilename($picFile).'.'. $picFile->guessExtension());

                //upload
                $this->fileHandler->upload($fileDir, $orgData->getPicturePath(), $picFile);

                $this->entityManager->flush();
                $orgData = $this->fileHandler->loadPicture($orgData);

                //if a picture already exist, need to remove it
                if($oldPic !== null){
                    $this->logger->logInfo(" User with id " . $this->getUser()->getId() . " remove old Picture for Organization with id ". $orgData->getId() );
                    $this->fileHandler->removeFile($fileDir.'/'.$oldPic);
                }

                $this->entityManager->flush();
                $orgData = $this->fileHandler->loadPicture($orgData);

            }else{
                $this->responseHandler->unauthorizedResponse("unauthorized");
            }

        }catch(Exception $e){
            $this->logger->logError($e,$this->getUser(),"error" );
            return $this->responseHandler->serverErrorResponse($e, "An error occured ");
        }

        //final response
        return $this->responseHandler->successResponse([$orgData]);
    }

    /**
     * @param Request $request
     * @return Response
     * @("/deletePicture", name="_picture_delete", methods="delete")
     */
    public function deletePicture(Request$request){
        // recover all data's request
        $this->parameters->setData($request);

        //check if required params exist
        try{ $this->parameters->hasData(["orgId"]); }
        catch(ViolationException $e) {
            $this->logger->logError($e, $this->getUser(), "error");
            return $this->responseHandler->BadRequestResponse($e->getViolationsList());
        }

        $repository = $this->entityManager->getRepository(User::class);
        try{
            //for no admin get org by user
            if($this->getUser()->getRoles()[0] !== "ROLE_ADMIN"){
                $repository = $this->entityManager->getRepository(User::class);
                $userData = $repository->findBy(["id" => $this->getUser()->getId()]);
                $user = $userData[0];

                $orgData = $user->getOrgById($this->parameters->getData("orgId"));
            }
            else{//for admin
                $repository = $this->entityManager->getRepository(Organization::class);
                $orgData = $repository->findBy(["id" => $this->parameters->getData("orgId")]);
                if(count($orgData) === 0 ){
                    $this->logger->logInfo(" Organization with id : ". $this->parameters->getData("orgId") ." not found " );
                    return $this->responseHandler->notFoundResponse();
                }
                $orgData = $orgData[0];
            }

            if($orgData !== false ){
                $this->fileHandler->removeFile('/pictures/User/' .$orgData->getPicturePath());
                $orgData->setPicturePath(null);

                $this->entityManager->flush();
                $userData = [$orgData];
            }else{
                $this->responseHandler->unauthorizedResponse("unauthorized");
            }

        }catch(Exception $e){
            $this->logger->logError($e,$this->getUser(),"error" );
            return $this->responseHandler->serverErrorResponse($e, "An error occured ");
        }

        $this->logger->logInfo(" User with id " . $user->getId() . " remove old Picture " );

        //final response
        return $this->responseHandler->successResponse($orgData, "read_org");
    }

    /**
     * @param Request $request
     * @return Response
     * @Route("/manageActivity", name="_manage_Activity", methods="put")
     */
    public function manageActivity(Request $request){
        // recover all data's request
        $this->parameters->setData($request);

        //check if required params exist
        try{ $this->parameters->hasData(["activityId", "orgId"]); }
        catch(ViolationException $e) {
            $this->logger->logError($e, $this->getUser(), "error");
            return $this->responseHandler->BadRequestResponse($e->getViolationsList());
        }


//get Activity
        $repository = $this->entityManager->getRepository(Activity::class);
        $actData = $repository->findBy(["id" => $this->parameters->getData("activityId")]);
        if(count($actData) === 0 ){
            $this->logger->logInfo(" Activity with id : ". $this->parameters->getData("activityId") ." not found " );
            return $this->responseHandler->notFoundResponse();
        }
        $actData = $actData[0];

//get organization
        $repository = $this->entityManager->getRepository(Organization::class);
        $orgData = $repository->findBy(["id" => $this->parameters->getData("orgId")]);
        if(count($orgData) === 0 ){
            $this->logger->logInfo(" Organization with id : ". $this->parameters->getData("orgId") ." not found " );
            return $this->responseHandler->notFoundResponse();
        }
        $orgData = $orgData[0];

//check manage access manage only for project creator, org referent and admin
        if($this->getUser()->getRoles()[0] !== "ROLE_ADMIN"){
            if(//if no referrent and no member && if no creator of the project
                $orgData->getReferent()->getId() !== $this->getUser()->getId()
                && $actData->getCreator()->getId() !== $this->getUser()->getId()

            ){
                return $this->responseHandler->unauthorizedResponse("unauthorized");
            }
        }

//if activity have the organization, remove it else add
        if($actData->getOrganization() !== null && $actData->getOrganization()->getId() === $orgData->getId()){
            $actData->setOrganization(null);
        }
        else { //add
            $actData->setOrganization($orgData);
        }

        $this->entityManager->flush();

        return $this->responseHandler->successResponse(["success"]);
    }

    /**
     * @param Request $request
     * @return Response
     * @Route("/manageProject", name="_manage_Project", methods="put")
     */
    public function manageProject(Request $request){
        // recover all data's request
        $this->parameters->setData($request);

        //check if required params exist
        try{ $this->parameters->hasData(["projectId", "orgId"]); }
        catch(ViolationException $e) {
            $this->logger->logError($e, $this->getUser(), "error");
            return $this->responseHandler->BadRequestResponse($e->getViolationsList());
        }

        try {
            //get Project
            $repository = $this->entityManager->getRepository(Project::class);
            $projData = $repository->findBy(["id" => $this->parameters->getData("projectId")]);
            if (count($projData) === 0) {
                $this->logger->logInfo(" Project with id : " . $this->parameters->getData("projectId") . " not found ");
                return $this->responseHandler->notFoundResponse();
            }
            $projData = $projData[0];


//get organization
            $repository = $this->entityManager->getRepository(Organization::class);
            $orgData = $repository->findBy(["id" => $this->parameters->getData("orgId")]);
            if (count($orgData) === 0) {
                $this->logger->logInfo(" Organization with id : " . $this->parameters->getData("orgId") . " not found ");
                return $this->responseHandler->notFoundResponse();
            }
            $orgData = $orgData[0];

//check manage access manage only for project creator, org referent and admin
            if($this->getUser()->getRoles()[0] !== "ROLE_ADMIN"){
                if(//if no referrent and no member && if no creator of the project
                    $orgData->getreferent()->getId() !== $this->getUser()->getId()
                    && $projData->getCreator()->getId() !== $this->getUser()->getId()

                ){
                    return $this->responseHandler->unauthorizedResponse("unauthorized");
                }
            }

//if activity have the organization, remove it
            if ($projData->getOrganization() !== null && $projData->getOrganization()->getId() === $orgData->getId()) {
                $projData->setOrganization(null);
            } else { //add
                $projData->setOrganization($orgData);
            }

            $this->entityManager->flush();

        }catch(Exception $e){
            $this->logger->logError($e,$this->getUser(),"error" );
            return $this->responseHandler->serverErrorResponse($e, "An error occured ");
        }

        return $this->responseHandler->successResponse(["success"]);
    }


    /**
     * return membered orgs for a user.
     * @param Request $request
     * @return Response
     * @Route("/membered", name="_membered", methods="get")
     */
    public function getOrgByUser (Request $request){
        try{
            $repository = $this->entityManager->getRepository(User::class);
            $userData = $repository->findBy(["id" => $this->getUser()->getId()])[0];
        }catch(Exception $e){
             $this->logger->logError($e,$this->getUser(),"error" );
            return $this->responseHandler->serverErrorResponse($e, "An error occured ");
        }

        //get refered and membered orgs,
        $orgsData = $userData->getMemberOf()->toArray();
        $orgsData = array_merge($orgsData, $userData->getOrganizations()->toArray());
    //    $orgsData = array_unique($orgsData);

        return $this->responseHandler->successResponse($orgsData);
    }
}
