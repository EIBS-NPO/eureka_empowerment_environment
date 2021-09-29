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
    private ParametersValidator $validator;
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
     * @param ParametersValidator $validator
     * @param EntityManagerInterface $entityManager
     * @param FileHandler $fileHandler
     * @param ActivityHandler $activityHandler
     * @param OrgHandler $orgHandler
     * @param ProjectHandler $projectHandler
     * @param LogService $logger
     */
    public function __construct(RequestParameters $requestParameters, ResponseHandler $responseHandler, ParametersValidator $validator, EntityManagerInterface $entityManager, FileHandler $fileHandler, ActivityHandler $activityHandler, OrgHandler $orgHandler, ProjectHandler $projectHandler, LogService $logger)
    {
        $this->parameters = $requestParameters;
        $this->responseHandler = $responseHandler;
        $this->validator = $validator;
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

    //check if required params exist
            $this->parameters->hasData(["id"]);

    //convert Date
            $this->parameters->addParam("postDate", New \DateTime("now"));

    //force boolean type
            if ($this->parameters->getData('isPublic') === "false") {
                $this->parameters->putData("isPublic", false);
            } else {
                $this->parameters->putData("isPublic", true);
            }

            $activity = $this->activityHandler->getActivities(
                $this->getUser(),
                [
                    "id" => $this->parameters->getData("id"),
                    "access" => "owned"
                ],
                true
            )[0];

    //handle potential link with an org
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

     //handle potential link with a project
             $projectId = $this->parameters->getData("project");
             if($projectId !== false){
                 $project = "null"; // by default for delete linking
                 if( $projectId !== "null"){
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




    /*
     * @param Request $request
     * @return Response
     * @Route("/picture", name="_picture_put", methods="post")
     */
   /* public function putPicture(Request $request ) :Response {
        try{
            // recover all data's request
            $this->parameters->setData($request);
            $this->parameters->hasData(["id", "pictureFile"]);

            //get activity by id with owned context and notFoundException
            $activity = $this->activityHandler->getActivities(
                $this->getUser(), [
                    "id" => $this->parameters->getData("id"),
                    "access" => "owned"],
                true
            )[0];

            $activity = $this->activityHandler->putPicture($activity,$this->parameters->getAllData());

            $activity = $this->activityHandler->withPictures([$activity]);

            return $this->responseHandler->successResponse($activity, "read_activity");
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
    }*/

    /**
     * @Route("/file", name="file_put", methods="post")
     * @param Request $request
     * @return Response|null
     */
    public function updateFile (Request $request): ?Response
    {
    try{
        // recover all data's request
        $this->parameters->setData($request);
        $this->parameters->hasData(["id", "file"]);

        //get activity by id with owned context and notFoundException
        $activity = $this->activityHandler->getActivities(
            $this->getUser(),
            [
                "id" => $this->parameters->getData("id"),
                "access" => "owned"
            ],
            true
        )[0];

        $activity = $this->activityHandler->putFile($activity, $this->parameters->getAllData());

        $activity = $this->activityHandler->withPictures([$activity]);
    return $this->responseHandler->successResponse($activity, "read_activity");
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
     * @return BinaryFileResponse|Response
     * @Route("/download/public", name="_download", methods="get")
     */
    public function downloadFile(Request $request)
    {
        try{
            // recover all data's request
            $this->parameters->setData($request);
            //check if required params exist
            $this->parameters->hasData(["id", "access"]);

            $activityFile = $this->activityHandler->getActivities(
                $this->getUser(),
                $this->parameters->getAllData(),
                true
            )[0];

            $file = $this->activityHandler->loadFile($activityFile, $this->getUser());

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

            $activities = $this->activityHandler->getActivities($this->getUser(), $this->parameters->getAllData());

            $activities = $this->activityHandler->withPictures($activities);

    return $this->responseHandler->successResponse($activities, "read_activity");
        }
    catch (Exception $e) {
            $this->logger->logError($e, $this->getUser(), "error");
            return $this->responseHandler->serverErrorResponse($e, "An error occured");
        }
    }

    /*
     * @param Request $request
     * @return Response
     * @Route("", name="_delete", methods="delete")
     */
 //   public function remove(Request $request) : Response {
        // recover all data's request
        /*$this->parameters->setData($request);

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
        }*/
  //  }

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
