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
use App\Services\Entity\OrgHandler;
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

    /**
     * OrgController constructor.
     * @param RequestParameters $requestParameters
     * @param ResponseHandler $responseHandler
     * @param ParametersValidator $validator
     * @param EntityManagerInterface $entityManager
     * @param FileHandler $fileHandler
     * @param LogService $logger
     * @param OrgHandler $orgHandler
     */
    public function __construct(RequestParameters $requestParameters, ResponseHandler $responseHandler, ParametersValidator $validator, EntityManagerInterface $entityManager, FileHandler $fileHandler, LogService $logger, OrgHandler $orgHandler)
    {
        $this->parameters = $requestParameters;
        $this->responseHandler = $responseHandler;
        $this->validator = $validator;
        $this->entityManager = $entityManager;
        $this->fileHandler = $fileHandler;
        $this->logger = $logger;

        $this->orgHandler = $orgHandler;
    }

//todo add pictureFile for add directly a picture into creation
    /**
     * @Route("", name="_registration", methods="post")
     * @param Request $request
     *      required Request Data ["name", "type", "email"]
     *      optionnals Request Data ["phone", 'description', "pictureFile"]
     * @return Response
     */
    public function create(Request $request) :Response
    {
        try{
            // recover all data's request
            $this->parameters->setData($request);
            $this->parameters->addParam("referent", $this->getUser());

            $newOrg = $this->orgHandler->createOrg( $this->parameters->getAllData());

            $newOrg = $this->orgHandler->withPictures([$newOrg]);

        return $this->responseHandler->successResponse($newOrg, "read_project");
        }
        catch(PartialContentException $e){
            $this->logger->logError($e, $this->getUser(), "error");
            return $this->responseHandler->partialResponse($e, "read_project");
        }
        catch(ViolationException $e) {
            $this->logger->logError($e, $this->getUser(), "error");
            return $this->responseHandler->BadRequestResponse($e->getMessage());
        }
        catch(UniqueConstraintViolationException $e){
                $this->logger->logError($e, $this->getUser(), "error");
                return $this->responseHandler->BadRequestResponse(json_encode(["name" => "Organization's name already exist"]));
        }
        catch(Exception $e){
            $this->logger->logError($e,$this->getUser(),"error");
            return $this->responseHandler->serverErrorResponse( "An error occured");
        }
    }


    /**
     * @Route("/public", name="_get_public", methods="get")
     * @param Request $request
     * @return Response
     */
    public function getPublic(Request $request) :Response
    {
        try{
            // recover all data's request
            $this->parameters->setData($request);
            $this->parameters->addParam("access", "public");

            $orgs = $this->orgHandler->getOrgs(null, $this->parameters->getAllData());

            $orgs = $this->orgHandler->withPictures($orgs);

        return $this->responseHandler->successResponse($orgs, "read_org");
        }
        catch(Exception $e){
            $this->logger->logError($e,$this->getUser(),"error" );
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
        try{
            // recover all data's request
            $this->parameters->setData($request);
            $orgs = $this->orgHandler->getOrgs($this->getUser(), $this->parameters->getAllData());

            $orgs = $this->orgHandler->withPictures($orgs);

        return $this->responseHandler->successResponse($orgs, "read_org");
        }
        catch (Exception $e) {//unexpected error
            $this->logger->logError($e, $this->getUser(), "error");
            return $this->responseHandler->serverErrorResponse("An error occurred");
        }
    }

    /**
     * @Route("/update", name="_put", methods="post")
     * @param Request $request
     * @return Response
     */
    public function updateOrganization(Request $request) :Response
    {
        try{
            $this->parameters->setData($request);
            $this->parameters->hasData(["id"]);

            //get org by id with owned context and notFoundException
            $org = $this->orgHandler->getOrgs(
                $this->getUser(), [
                "id" => $this->parameters->getData("id"),
                "access" => "owned"],
                true
            )[0];
            $org = $this->orgHandler->updateOrg($org, $this->parameters->getAllData());

            $org = $this->orgHandler->withPictures([$org]);

        return $this->responseHandler->successResponse($org, "read_org");
        }
        catch(ViolationException | NoFoundException $e) {
            $this->logger->logError($e, $this->getUser(), "error");
            return $this->responseHandler->BadRequestResponse($e->getMessage());
        }
        catch (Exception $e) {//unexpected error
            $this->logger->logError($e, $this->getUser(), "error");
            return $this->responseHandler->serverErrorResponse("An error occurred");
        }
    }

    /**
     * @param Request $request
     * @return Response
     * @Route("/picture", name="_picture_put", methods="post")
     */
    public function putPicture(Request $request ) :Response {
       try{
           // recover all data's request
           $this->parameters->setData($request);
           $this->parameters->hasData(["id", "pictureFile"]);

           //get org by id with owned context and notFoundException
           $org = $this->orgHandler->getOrgs(
               $this->getUser(), [
               "id" => $this->parameters->getData("id"),
               "access" => "owned"],
               true
           )[0];

           $org = $this->orgHandler->putPicture($org,$this->parameters->getAllData());

           $org = $this->orgHandler->withPictures([$org]);

       return $this->responseHandler->successResponse($org, "read_org");
       }
       catch(ViolationException | NoFoundException $e) {
           $this->logger->logError($e, $this->getUser(), "error");
           return $this->responseHandler->BadRequestResponse($e->getMessage());
       }
       catch (BadMediaFileException $e){
           $this->logger->logError($e, $this->getUser(), "error");
           return $this->responseHandler->BadMediaResponse($e->getMessage());
       }
       catch (Exception $e) {//unexpected error
           $this->logger->logError($e, $this->getUser(), "error");
           return $this->responseHandler->serverErrorResponse("An error occurred");
       }
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


    /*
     * return membered orgs for a user.
     * @param Request $request
     * @return Response
     * @Route("/membered", name="_membered", methods="get")
     */
    /*public function getOrgByUser (Request $request){
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
    }*/
}
