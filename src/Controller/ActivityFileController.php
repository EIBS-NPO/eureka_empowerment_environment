<?php

namespace App\Controller;

use App\Entity\Activity;
use App\Entity\ActivityFile;
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
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class ActivityFileController
 * @package App\Controller
 * @Route("/file", name="file")
 */
class ActivityFileController extends AbstractController
{
    private RequestParameters $parameters;
    private ResponseHandler $responseHandler;
    private ParametersValidator $validator;
    protected EntityManagerInterface $entityManager;
    protected FileHandler $fileHandler;
    private LogService $logger;

    /**
     * UserController constructor.
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
     * @Route("/create", name="_post", methods="post")
     * @param Request $request
     * @return Response
     */
    public function postFile(Request $request): Response
    {
        // recover all data's request
        $this->parameters->setData($request);
        $this->parameters->addParam("creator", $this->getUser());

//check if required params exist
        try{ $this->parameters->hasData(["file", "id"]); }
        catch(ViolationException $e) {
            $this->logger->logError($e, $this->getUser(), "error");
            return $this->responseHandler->BadRequestResponse($e->getViolationsList());
        }

        $file = $this->parameters->getData("file");

        try {
            //get the activity
            $repository = $this->entityManager->getRepository(Activity::class);
            $activityData = $repository->findBy(["id" => $this->parameters->getData("id")]);

            if(get_class($activityData[0]) === Activity::class) {
                //keep safe for deleting later if upload success
                $activity = $activityData[0];

                //create a new activityFile and hydrate it with activity data
                $activityData = new ActivityFile();
                $activityData->setForActivity($activity);
            }

            try{
                $this->fileHandler->isAllowedMime($file);
            }catch (Exception $e){
                $this->logger->logError($e,$this->getUser(),"error" );
                return $this->responseHandler->BadMediaResponse($e->getMessage());
            }

            try{
                $activityData->setFilename($this->fileHandler->getOriginalFilename($file).".".$file->guessExtension());
                $activityData->setFileType($file->getMimeType());
                $activityData->setSize($file->getSize());
                $activityData->setUniqId(uniqid());

                //make new filename
                $completName = $activityData->getUniqId(). '_'. $activityData->getFilename();

                //upload
                $this->fileHandler->upload('/files/Activity', $completName, $file);

                //make new checksum
                $activityData->setChecksum($this->fileHandler->getChecksum('/files/Activity/'. $completName));

            }catch(Exception $e){
                $this->logger->logError($e, $this->getUser(),"error");
                $this->logger->logEvent($this->getUser(),$activityData->getId(), ActivityFile::class, "SERVER_ERROR : upload_File FAILED");
                $this->responseHandler->serverErrorResponse($e, "An error occurred");
            }

        }catch(Exception $e){
            $this->logger->logError($e,$this->getUser(),"error" );
            return $this->responseHandler->serverErrorResponse($e, "An error occured ");
        }

        //persist the new activity
        try{
            $this->entityManager->persist($activityData);
            $this->entityManager->flush();

            if(isset($activity)){
                $this->entityManager->remove($activity);
            }

        }catch(Exception $e){
            $this->logger->logError($e,$this->getUser(),"error");
            return $this->responseHandler->serverErrorResponse($e, "An error occured");
        }

        $this->logger->logInfo("ActivityFile id " . $activityData->getId() . " uploaded File " );
        $this->logger->logEvent($this->getUser(),$activityData->getId(), ActivityFile::class, "uploaded file");

        if($activityData->getPicturePath()){
            $activityData = [$this->fileHandler->loadPicture($activityData)];
        }

        //success response
        return $this->responseHandler->successResponse([$activityData], "read_activity");
    }


    /**
     * @param Request $request
     * @return Response|null
     * @Route("/update", name="_put", methods="post")
     */
    public function updateActivityFile (Request $request) {
        // recover all data's request
        $this->parameters->setData($request);

        //check if required params exist
        try{ $this->parameters->hasData(["file", "id"]); }
        catch(ViolationException $e) {
            $this->logger->logError($e, $this->getUser(), "error");
            return $this->responseHandler->BadRequestResponse($e->getViolationsList());
        }

        try{
            //get the activity
            $repository = $this->entityManager->getRepository(Activity::class);
            $activityData = $repository->findBy(["id" => $this->parameters->getData("id") ] )[0];

            $file = $this->parameters->getData("file");

            $activityData->setFilename($this->fileHandler->getOriginalFilename($file).".".$file->guessExtension());
            $activityData->setFileType($file->getMimeType());
            $activityData->setSize($file->getSize());
            $activityData->setUniqId(uniqid());

            //make new filename
            $completName = $activityData->getUniqId(). '_'. $activityData->getFilename();

            //upload
            $this->fileHandler->upload('/files/Activity', $completName, $file);

            //make new checksum
            $activityData->setChecksum($this->fileHandler->getChecksum('/files/Activity/'. $completName));

            //persist updated activityFile
            $this->entityManager->flush();

        }catch(Exception $e){
            $this->logger->logError($e, $this->getUser(),"error");
            $this->logger->logEvent($this->getUser(),$activityData->getId(), ActivityFile::class, "SERVER_ERROR : upload_File FAILED");
            $this->responseHandler->serverErrorResponse($e, "An error occurred");
        }

        //persist updated activityFile
        $this->entityManager->flush();

        if($activityData->getPicturePath()){
            $activityData = [$this->fileHandler->loadPicture($activityData)];
        }

        //success response
        return $this->responseHandler->successResponse([$activityData], "read_activity");
    }




    //todo vraiment utilisé?

    /**
     * @param Request $request
     * @return Response|null
     * @Route("", name="_get", methods="get")
     */
    public function getActivityFile (Request $request)
    {
        // recover all data's request
        $this->parameters->setData($request);

        //check if required params exist
        try {
            $this->parameters->hasData(["id"]);
        } catch (ViolationException $e) {
            $this->logger->logError($e, $this->getUser(), "error");
            return $this->responseHandler->BadRequestResponse($e->getViolationsList());
        }

        $repository = $this->entityManager->getRepository(Activity::class);
        //get query, if id not define, query getALL
        try {
            $activityData = $repository->findBy(["id" => $this->parameters->getData("id")]);
        } catch (Exception $e) {
            $this->logger->logError($e, $this->getUser(), "error");
            return $this->responseHandler->serverErrorResponse($e, "An error occured");
        }

        if (count($activityData) > 0) {
            $activityData = $activityData[0];
            $completName = $activityData->getUniqId() . '_' . $activityData->getFilename();

            //todo a finir!
            if (get_class($activityData) === ActivityFile::class) {
                try {
                    $this->fileHandler->controlChecksum('/files/Activity/' . $completName, $activityData->getChecksum());
                } catch (Exception $e) {
                    $this->logger->logError($e, $this->getUser(), "warning");
                    $this->logger->logEvent($this->getUser(), $activityData->getId(), ActivityFile::class, "SERVER_ERROR : checksum COMPARISON FAILED");
                }

            }

            //check if public or private data return
            if (!$activityData->hasAccess($this->getUser())) {
                $activityData->setActivities($activityData->getOnlyPublicActivities());
            }

            if ($activityData->getPicturePath()) {
                $activityData = [$this->fileHandler->loadPicture($activityData)];
            }

            //success response
            return $this->responseHandler->successResponse([$activityData], "read_activity");
        }else {
            return $this->responseHandler->notFoundResponse();
        }
    }

    /**
     * @param Request $request
     * @return Response|null
     * @Route("", name="_delete", methods="delete")
     */
    public function removeActivityFile (Request $request){
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

                $activityData = $user->getActivity($this->parameters->getData("id"))[0];
            } else {//for admin
                $repository = $this->entityManager->getRepository(Activity::class);
                $activityData = $repository->findBy(["id" => $this->parameters->getData("id")]);
                if (count($activityData) === 0) {
                    $this->logger->logInfo(" Activity with id : " . $this->parameters->getData("id") . " not found ");
                    return $this->responseHandler->notFoundResponse();
                }
                $activityData = $activityData[0];
            }

            $simpleActivity = new Activity();
            $simpleActivity->setFromActivityFile($activityData);

            $this->entityManager->persist($simpleActivity);
            $this->entityManager->flush();

            $this->entityManager->remove($activityData);
            $this->entityManager->flush();

            if($simpleActivity->getPicturePath()){
                $simpleActivity = [$this->fileHandler->loadPicture($simpleActivity)];
            }

            return $this->responseHandler->successResponse([$simpleActivity], "read_activity");

        }catch(Exception $e){
            $this->logger->logError($e,$this->getUser(),"error" );
            return $this->responseHandler->serverErrorResponse($e, "An error occured");
        }
    }


    /**
     * @param Request $request
     * @return Response|null
     * @Route("/download/public", name="_public_download", methods="get")
     */
    public function downloadPublicFile(Request $request)
    {
        // recover all data's request
        $this->parameters->setData($request);


        //check if required params exist
        try{ $this->parameters->hasData(["id"]); }
        catch(ViolationException $e) {
            $this->logger->logError($e, $this->getUser(), "error");
            return $this->responseHandler->BadRequestResponse($e->getViolationsList());
        }

    try{
        $repository = $this->entityManager->getRepository(ActivityFile::class);
        $activityData = $repository->findBy([
            "id" => $this->parameters->getData("id"),
            "isPublic" => true
        ]);

        if (count($activityData) === 0) {
            $this->logger->logInfo(" ActivityFile with id : " . $this->parameters->getData("id") . " not found ");
            return $this->responseHandler->notFoundResponse();
        }

        if(!empty($activityData)){
            $activityData = $activityData[0];
            $completName = $activityData->getUniqId(). '_'. $activityData->getFilename();
            $file = $this->fileHandler->getFile('/files/Activity/'.$completName);

            if(!$this->fileHandler->controlChecksum('/files/Activity/'.$completName, $activityData->getChecksum())){
                return $this->responseHandler->CorruptResponse("Compromised file");
            }


            $response = new BinaryFileResponse($file);
            $response->headers->set('Content-Type',$activityData->getFileType());

            $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $activityData->getFilename());
            return $response;
        }else{
            return $this->responseHandler->notFoundResponse();
        }

        }catch(Exception $e){
            $this->logger->logError($e,$this->getUser(),"error" );
            return $this->responseHandler->serverErrorResponse($e, "An error occured");
        }
    }

    /**
     * @param Request $request
     * @return BinaryFileResponse|Response
     * @Route("/download", name="_download", methods="get")
     */
    public function downloadFile(Request $request)
    {
        // recover all data's request
        $this->parameters->setData($request);

        //check if required params exist
        try{ $this->parameters->hasData(["id"]); }
        catch(ViolationException $e) {
            $this->logger->logError($e, $this->getUser(), "error");
            return $this->responseHandler->BadRequestResponse($e->getViolationsList());
        }

        try{
            $repository = $this->entityManager->getRepository(ActivityFile::class);
            $activityData = $repository->findBy(["id" => $this->parameters->getData("id")]);
        }catch(Exception $e){
            $this->logger->logError($e,$this->getUser(),"error" );
            return $this->responseHandler->serverErrorResponse($e, "An error occured");
        }

        if (!$activityData[0]->hasAccess($this->getUser())) {
            return $this->responseHandler->unauthorizedResponse("unauthorized");
        }

        if(!empty($activityData[0])){
            $activityFile = $activityData[0];

            $path = '/files/Activity/'.$activityFile->getUniqId(). '_'. $activityFile->getFilename();
            $file = $this->fileHandler->getFile($path);

            if(!$this->fileHandler->controlChecksum($path, $activityFile->getChecksum())){
                return $this->responseHandler->CorruptResponse("Compromised file");
            }

            $response = new BinaryFileResponse($file);
            $response->headers->set('Content-Type',$activityFile->getFileType());

            $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $activityFile->getFilename());
        }
        else { return $this->responseHandler->notFoundResponse();}

        return $response;
    }
}
