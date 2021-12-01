<?php

namespace App\Controller;

use App\Entity\Activity;
use App\Entity\ActivityFile;
use App\Entity\Project;
use App\Entity\User;
use App\Exceptions\BadMediaFileException;
use App\Exceptions\NoFoundException;
use App\Exceptions\PartialContentException;
use App\Exceptions\ViolationException;
use App\Services\Configuration\ConfigurationHandler;
use App\Services\Entity\ActivityHandler;
use App\Services\Entity\OrgHandler;
use App\Services\Entity\ProjectHandler;
use App\Services\FileHandler;
use App\Services\LogService;
use App\Services\Request\ParametersValidator;
use App\Services\Request\RequestParameters;
use App\Services\Request\ResponseHandler;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\Exception\NoFileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class ActivityController
 * @package App\Controller
 * @Route("/activity", name="activity")
 */
class ActivityController extends AbstractController
{
    private RequestParameters $parameters;
    private ResponseHandler $responseHandler;
    protected EntityManagerInterface $entityManager;
    protected FileHandler $fileHandler;
    private LogService $logger;
    private ActivityHandler $activityHandler;
    private OrgHandler $orgHandler;
    private ProjectHandler $projectHandler;

    /**
     * UserController constructor.
     * @param RequestParameters $requestParameters
     * @param ResponseHandler $responseHandler
     * @param EntityManagerInterface $entityManager
     * @param FileHandler $fileHandler
     * @param ActivityHandler $activityHandler
     * @param OrgHandler $orgHandler
     * @param ProjectHandler $projectHandler
     * @param LogService $logger
     */
    public function __construct(RequestParameters $requestParameters, ResponseHandler $responseHandler, EntityManagerInterface $entityManager, FileHandler $fileHandler, ActivityHandler $activityHandler, OrgHandler $orgHandler, ProjectHandler $projectHandler, LogService $logger)
    {
        $this->parameters = $requestParameters;
        $this->responseHandler = $responseHandler;
        $this->entityManager = $entityManager;
        $this->fileHandler = $fileHandler;
        $this->orgHandler = $orgHandler;
        $this->projectHandler = $projectHandler;
        $this->logger = $logger;

        $this->activityHandler = $activityHandler;
    }


    /**
     * @Route("", name="_post", methods="post")
     * @param Request $request
     * @return Response
     */
    public function create(Request $request): Response
    {
        try {
            // recover all data's request
            $this->parameters->setData($request);

            $this->parameters->addParam("creator", $this->getUser());
            $this->parameters->addParam("postDate", New \DateTime("now"));

            //force boolean type
            if(!$this->parameters->getData('isPublic') || $this->parameters->getData('isPublic') === "false"){
                $this->parameters->putData("isPublic", false);
            }else{$this->parameters->putData("isPublic", true);}

            $activity = $this->activityHandler->create($this->getUser(), $this->parameters->getAllData());

            $activity = $this->activityHandler->withPictures([$activity]);

        return $this->responseHandler->successResponse($activity);
        }
        catch(PartialContentException $e){
            $this->logger->logError($e, $this->getUser(), "error");
            return $this->responseHandler->partialResponse($e, "read_activity");
        }
        catch(ViolationException $e) {
            $this->logger->logError($e, $this->getUser(), "error");
            return $this->responseHandler->BadRequestResponse($e->getMessage());
        }
        catch(Exception $e){
            $this->logger->logError($e,$this->getUser(),"error");
            return $this->responseHandler->serverErrorResponse( "An error occured");
        }

    }


    /**
     * @Route("/update", name="_put", methods="post")
     * @param Request $request
     * @return Response
     */
    public function updateActivity (Request $request) :Response
    {
     try   {// recover all data's request
            $this->parameters->setData($request);

         $getParams = [];
         $getParams["access"] = "search"; //force owned access
     //check if admin access required
         if($this->parameters->getData("admin")!== false){
             $this->denyAccessUnlessGranted('ROLE_ADMIN');
             $getParams["admin"] = true;
         //    $getParams["access"] = "search"; //for allowed admin to access
         }

    //check if required params exist
            $this->parameters->hasData(["id"]);
            $getParams["id"] = $this->parameters->getData("id");

    //convert Date
            $this->parameters->addParam("postDate", New \DateTime("now"));

    //force boolean type
         $publicParam = $this->parameters->getData('isPublic');
            if ($publicParam !== false) {
                if($publicParam === "false"){
                    $this->parameters->putData("isPublic", false);
                }
                else {
                    $this->parameters->putData("isPublic", true);
                }
            }

    //retrieve activity targeted
            $activity = $this->activityHandler->getActivities(
                $this->getUser(),
                $getParams,
                true
            )[0];

    //retrieve org for potential relation handled
            $orgId = $this->parameters->getData("organization");
            if($orgId !== false){
                $org = "null"; //by default for delete linking
                if( $orgId !== "null"){
                    $org = $this->orgHandler->getOrgs(
                        $this->getUser(),
                        ["id" => $orgId],
                        true
                    )[0];
                }
                $this->parameters->putData("organization", $org);
            }

     //retrieve project for potential relation handled
             $projectId = $this->parameters->getData("project");
             if($projectId !== false){
                 $project = "null"; // by default for delete linking
                 if( is_numeric($projectId)){
                     $project = $this->projectHandler->getProjects(
                         $this->getUser(),
                         ["id" => $projectId],
                         true
                     )[0];
                 }
                 $this->parameters->putData("project", $project);
             }

            $activity = $this->activityHandler->update(
                $this->getUser(),
                $activity,
                $this->parameters->getAllData()
            );

            $activity = $this->activityHandler->withPictures([$activity]);
    //success response
    return $this->responseHandler->successResponse($activity, "read_activity");
        }
    catch (ViolationException | NoFoundException $e) {
         $this->logger->logError($e, $this->getUser(), "error");
         return $this->responseHandler->BadRequestResponse($e->getMessage());
        }
    catch (Exception $e) {//unexpected error
         $this->logger->logError($e, $this->getUser(), "error");
         return $this->responseHandler->serverErrorResponse("An error occurred");
        }
    }


    /**
     * @Route("/download", name="_download", methods="get")
     * @param Request $request
     * @return BinaryFileResponse|Response
     */
    public function downloadFile(Request $request)
    {
        try{
            // recover all data's request
            $this->parameters->setData($request);
            //check if required params exist
            $this->parameters->hasData(["id"]);

            //force search access
            //check if admin access required
            if($this->parameters->getData("admin")!== false){
                //todo deprecated in Symfony 5.3 ?
                $this->denyAccessUnlessGranted('ROLE_ADMIN');
                $this->parameters->putData("admin", true);
              //  $getParams["access"] = "search"; //for allowed admin to access
            }
           /* if($this->parameters->getData("access") === "admin"){
                //todo deprecated with Symfony 5.3 ?
                $this->denyAccessUnlessGranted('ROLE_ADMIN');
            }*/

            $activityFile = $this->activityHandler->getActivities(
                $this->getUser(),
                $this->parameters->getAllData(),
                true
            )[0];

            $file = $this->activityHandler->loadFile(
                $activityFile,
                $this->parameters->getData("admin"),
                $this->getUser()
            );

            $response = new BinaryFileResponse($file);
            $response->headers->set('Content-Type',$activityFile->getFileType());

            $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $activityFile->getFilename());

        return $response;
        }
        catch(NoFoundException $e){
            $this->logger->logError($e, $this->getUser(), "error");
            return $this->responseHandler->notFoundResponse();
        }
        catch(ViolationException | NoFileException $e) {
            $this->logger->logError($e, $this->getUser(), "error");
            return $this->responseHandler->BadRequestResponse($e->getMessage());
        }catch(UnauthorizedHttpException $e){
            return $this->responseHandler->unauthorizedResponse($e->getMessage());
        }
        catch(Exception $e){
            $this->logger->logError($e,$this->getUser(),"error" );
            return $this->responseHandler->serverErrorResponse($e, "An error occured");
        }
    }

    /**
     * @param Request $request
     * @return BinaryFileResponse|Response
     * @Route("/download/public", name="public_download", methods="get")
     */
    public function downloadPublic(Request $request)
    {
        try{
            // recover all data's request
            $this->parameters->setData($request);
            //check if required params exist
            $this->parameters->hasData(["id"]);

            $activityFile = $this->activityHandler->getActivities(
                null,
                $this->parameters->getAllData(),
                true
            )[0];

            $file = $this->activityHandler->loadFile(
                $activityFile,
                $this->parameters->getData("access"),
                $this->getUser()
            );

            $response = new BinaryFileResponse($file);
            $response->headers->set('Content-Type',$activityFile->getFileType());

            $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $activityFile->getFilename());

            return $response;
        }
        catch(NoFoundException $e){
            $this->logger->logError($e, $this->getUser(), "error");
            return $this->responseHandler->notFoundResponse();
        }
        catch(ViolationException | NoFileException $e) {
            $this->logger->logError($e, $this->getUser(), "error");
            return $this->responseHandler->BadRequestResponse($e->getMessage());
        }catch(UnauthorizedHttpException $e){
            return $this->responseHandler->unauthorizedResponse($e->getMessage());
        }
        catch(Exception $e){
            $this->logger->logError($e,$this->getUser(),"error" );
            return $this->responseHandler->serverErrorResponse($e, "An error occured");
        }
    }

    /**
     * returns all public activities
     * @Route("/public", name="_get_public", methods="get")
     * @param Request $request
     * @return Response
     */
    public function getPublic(Request $request): Response {

        try{
            // recover all data's request
            $this->parameters->setData($request);
        //    $this->parameters->hasData(["access"]);

            $activities = $this->activityHandler->getActivities(
                null,
                $this->parameters->getAllData());

            $activities = $this->activityHandler->withPictures($activities);

            return $this->responseHandler->successResponse($activities, "read_activity");
        }
        catch(Exception $e){
            $this->logger->logError($e,$this->getUser(),"error" );
            return $this->responseHandler->serverErrorResponse($e, "An error occured");
        }
    }

    /**
     * returns to a user his created activities
     * @Route("", name="_get", methods="get")
     * @param Request $request
     * @return Response
     */
    public function getPrivate(Request $request): Response
    {
    try{
            // recover all data's request
            $this->parameters->setData($request);
         //   $this->parameters->hasData(["access"]);

        //check if admin access required
        if($this->parameters->getData("admin") !== false){
            $this->denyAccessUnlessGranted('ROLE_ADMIN');
        }

            $activities = $this->activityHandler->getActivities($this->getUser(), $this->parameters->getAllData());

            $activities = $this->activityHandler->withPictures($activities);

    return $this->responseHandler->successResponse($activities, "read_activity");
        }
    catch (Exception $e) {
            $this->logger->logError($e, $this->getUser(), "error");
            return $this->responseHandler->serverErrorResponse("An error occured");
        }
    }

    /**
     * @param Request $request
     * @param ConfigurationHandler $configHandler
     * @return Response
     * @Route("/allowed/public", name="_allowed", methods="get")
     */
    public function getAllowedFileFormat(Request $request, ConfigurationHandler $configHandler) :Response{

        try {
            $allowedMime = $configHandler->getValue("mime.type.allowed");
            return $this->responseHandler->successResponse($allowedMime);
        }
        catch (Exception $e) {
            $this->logger->logError($e, $this->getUser(), "error");
            return $this->responseHandler->serverErrorResponse("An error occured");
        }
    }

}
