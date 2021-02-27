<?php

namespace App\Controller;

use App\Entity\Activity;
use App\Entity\ActivityFile;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class ActivityFileController
 * @package App\Controller
 * @Route("/file", name="file")
 */
class ActivityFileController extends CommonController
{
    /**
     * @Route("/create", name="_post", methods="post")
     * @param Request $insecureRequest
     * @return Response
     */
    public function postFile(Request $insecureRequest): Response
    {
        //cleanXSS
        if($this->cleanXSS($insecureRequest)) return $this->response;

        // recover all data's request
        $this->dataRequest = $this->requestParameters->getData($this->request);
        $this->dataRequest = array_merge($this->dataRequest, ["creator" => $this->getUser()->getId()]);

        if(!isset($this->dataRequest['file']) || (!isset($this->dataRequest['id']))){
            return $this->BadRequestResponse(["missing params"]);
        }

        //getActivity query by id and creatorId
        if($this->getEntities(Activity::class, ['id', 'creator'])) return $this->response;

        if(get_class($this->dataResponse[0]) === Activity::class) {
            //keep safe for deleting later if upload success
            $this->dataRequest['simpleActivity'] = $this->dataResponse[0];

            $activityFile = new ActivityFile();
            $activityFile->setForActivity($this->dataResponse[0]);
            $this->dataResponse[0] = $activityFile;
        }

        if($this->uploadFile($this->dataResponse[0], $this->dataRequest['file'])) return $this->response;

        if($this->persistEntity($this->dataResponse[0])) return $this->response;
        $newActivityFile = $this->dataResponse[0];

        $this->logService->logInfo("ActivityFile id " . $newActivityFile->getId() . " uploaded File " );
        $this->logService->logEvent($this->getUser(),$newActivityFile->getId(), $this->getClassName($newActivityFile), "uploaded file");

        if(isset($this->dataRequest['simpleActivity'])){
            if($this->deleteEntity($this->dataRequest['simpleActivity'])) return $this->response;
        }

        $this->dataResponse = [$newActivityFile];
        if($this->dataResponse[0]->getPicturePath()){
            $this->dataResponse = [$this->loadPicture($this->dataResponse[0])];
        }

        //success response
        return $this->successResponse();
    }


    /**
     * @param Request $insecureRequest
     * @return Response|null
     * @Route("/update", name="_put", methods="post")
     */
    public function updateActivityFile (Request $insecureRequest) {
        if($this->cleanXSS($insecureRequest)
        ) return $this->response;

        // recover all data's request
        $this->dataRequest = $this->requestParameters->getData($this->request);

        if(!isset($this->dataRequest['file']) || (!isset($this->dataRequest['id']))){
            return $this->BadRequestResponse(["missing params"]);
        }

        if($this->getEntities(ActivityFile::class, ['id'])) return $this->response;

        if($this->uploadFile($this->dataResponse[0], $this->dataRequest['file'])) return $this->response;

        //persist updated activityFile
        if ($this->updateEntity($this->dataResponse[0])) return $this->response;

        $this->dataResponse = [$this->loadPicture($this->dataResponse[0])];

        return $this->successResponse();
    }




    /**
     * @param Request $insecureRequest
     * @return Response|null
     * @Route("", name="_get", methods="get")
     */
    public function getActivityFile (Request $insecureRequest) {
        if($this->cleanXSS($insecureRequest)
        ) return $this->response;

        // recover all data's request
        $this->dataRequest = $this->requestParameters->getData($this->request);
        if(!isset($this->dataRequest['id'])) return $this->notFoundResponse();

        if($this->getEntities(Activity::class, ['id'])) return $this->response;

        $activityFile = $this->dataResponse[0];
        $completName = $activityFile->getUniqId(). '_'. $activityFile->getFilename();

        //todo a finir!
        if(get_class($this->dataResponse[0]) === ActivityFile::class){
            $this->fileHandler->controlChecksum('/files/Activity/'.$completName, $activityFile->getChecksum());
        }

        $this->dataResponse = [$this->loadPicture($activityFile)];

        //success response
        return $this->successResponse();
    }

    /**
     * @param Request $insecureRequest
     * @return Response|null
     * @Route("", name="_delete", methods="delete")
     */
    public function removeActivityFile (Request $insecureRequest){
        if($this->cleanXSS($insecureRequest)
        ) return $this->response;

        // recover all data's request
        $this->dataRequest = $this->requestParameters->getData($this->request);
        if(!isset($this->dataRequest['id'])) return $this->BadRequestResponse(["missing parameter : id is required. "]);

        if($this->getEntities(ActivityFile::class, ['id'])) return $this->response;
        $this->dataRequest['activityFile'] = $this->dataResponse[0];
        $simpleActivity = new Activity();
        $simpleActivity->setFromActivityFile($this->dataResponse[0]);

        if($this->persistEntity($simpleActivity)) return $this->response;
        $simpleActivity = $this->dataResponse[0];

        if($this->deleteEntity($this->dataRequest['activityFile'])) return $this->response;

        $this->dataResponse = [$simpleActivity];
        if($this->dataResponse[0]->getPicturePath()){
            $this->dataResponse = [$this->loadPicture($this->dataResponse[0])];
        }

        return $this->successResponse();
    }


    /**
     * @param Request $insecureRequest
     * @return Response|null
     * @Route("/download/public", name="_public_download", methods="get")
     */
    public function downloadPublicFile(Request $insecureRequest)
    {
        //comme route public en ano, il ne trouve pas l'interfaceUser?
    //    dd($this->getUser());
        if($this->cleanXSS($insecureRequest)
        ) return $this->response;

        // recover all data's request
        $this->dataRequest = $this->requestParameters->getData($this->request);
        if(!isset($this->dataRequest['id'])) return $this->BadRequestResponse(["missing parameter : id is required. "]);
        $this->dataRequest["isPublic"] = true;

        if($this->getEntities(ActivityFile::class, ['id', 'isPublic'])) return $this->response;

        if($this->getFile($this->dataResponse[0])) return $this->response;
        $file = $this->dataResponse[0];

        $response = new BinaryFileResponse($file);
        $response->headers->set('Content-Type',$this->dataResponse[0]->getFileType());

        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $this->dataResponse[0]->getFilename());

        return $response;
    }

    /**
     * @param Request $insecureRequest
     * @Route("/download", name="_download", methods="get")
     */
    public function downloadFile(Request $insecureRequest)
    {
        if($this->cleanXSS($insecureRequest)
        ) return $this->response;

        // recover all data's request
        $this->dataRequest = $this->requestParameters->getData($this->request);
        if(!isset($this->dataRequest['id'])) return $this->BadRequestResponse(["missing parameter : id is required. "]);


        if($this->getEntities(ActivityFile::class, ['id'])) return $this->response;

        $activityFile = $this->dataResponse[0];

        if($this->getFile($this->dataResponse[0])) return $this->response;
        $file = $this->dataResponse[0];

        $response = new BinaryFileResponse($file);
        $response->headers->set('Content-Type',$activityFile->getFileType());

        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $activityFile->getFilename());

        return $response;
    }

    public function streamFileResponse($file, $filename) : StreamedResponse
    {
        $response = new StreamedResponse(static function () use ($file) {
            $outputStream = fopen('php://output', 'wb');

            stream_copy_to_stream($file, $outputStream);
        });

        $response->headers->set('Content-Type', $file->getType());

        $disposition = HeaderUtils::makeDisposition(
            HeaderUtils::DISPOSITION_ATTACHMENT,
            $filename
        );
        $response->headers->set('Content-Disposition', $disposition);

        return $response;
    }
}
