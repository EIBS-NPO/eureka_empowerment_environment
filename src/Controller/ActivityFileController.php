<?php

namespace App\Controller;

use App\Entity\Activity;
use App\Entity\ActivityFile;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
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
     * @Route("", name="_post", methods="post")
     * @param Request $insecureRequest
     * @return Response
     */
    public function postFile(Request $insecureRequest): Response
    {
        //cleanXSS
        if($this->cleanXSS($insecureRequest)
        ) return $this->response;

        // recover all data's request
        $this->dataRequest = $this->requestParameters->getData($this->request);
        $this->dataRequest = array_merge($this->dataRequest, ["creator" => $this->getUser()->getId()]);

        //todo comment for test
        if(!isset($this->dataRequest['file']) || (!isset($this->dataRequest['id']))){
            //todo return badRequest?
            return $this->notFoundResponse();
        }

        //getActivity query by id and creatorId
        if($this->getEntities(Activity::class, ['id', 'creator'])) return $this->response;
        $activity = $this->dataResponse[0];

        //i factivity already have a activityFile
        if($this->dataResponse[0]->getFilePath() === null){
            $activityFile = new ActivityFile();
            $activityFile->setForActivity($activity);
        }else {
            if($this->getEntities(ActivityFile::class, ['id', 'creator'])) return $this->response;
            $activityFile = $this->dataResponse[0];
        }

        //creer activityFile (need FilePath, FileType et checkSum)
        if($this->uploadFile($activityFile, $this->dataRequest['file'])) return $this->response;

        //persist the new activityFile
        if($this->persistEntity($this->dataResponse[0])) return $this->response;

        //success response
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

        //todo checksum
        $activity = $this->dataResponse;

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
        if(!isset($this->dataRequest['id'])) return $this->notFoundResponse();

        if($this->getEntities(ActivityFile::class, ['id'])) return $this->response;

        $this->deleteEntity($this->dataResponse[0]);

        return $this->successResponse();
    }

    /**
     * @param Request $insecureRequest
     * @return Response|null
     * @Route("", name="_delete", methods="put")
     */
    public function updateActivityFile (Request $insecureRequest) {
        if($this->cleanXSS($insecureRequest)
        ) return $this->response;

        // recover all data's request
        $this->dataRequest = $this->requestParameters->getData($this->request);
        if(!isset($this->dataRequest['id'])) return $this->notFoundResponse();

        if($this->getEntities(ActivityFile::class, ['id'])) return $this->response;
        $file = $this->dataResponse[0];

        //persist updated user
        if ($this->updateEntity($file)) return $this->response;

        return $this->successResponse();
    }

    /**
     * @param Request $insecureRequest
     * @return Response|null
     * @Route("/download/public", name="_public_download", methods="get")
     */
    public function downloadPublicActivityFile(Request $insecureRequest)
    {
        if($this->cleanXSS($insecureRequest)
        ) return $this->response;

        // recover all data's request
        $this->dataRequest = $this->requestParameters->getData($this->request);
        if(!isset($this->dataRequest['id'])) return $this->notFoundResponse();
        $this->dataRequest["isPublic"] = true;

        if($this->getEntities(ActivityFile::class, ['id', 'isPublic'])) return $this->response;

        if($this->getFile($this->dataResponse[0])) return $this->response;

        $file = $this->dataResponse[0];

       return $this->streamFileResponse($file);
    }

    /**
     * @param Request $insecureRequest
     * @Route("/download", name="_download", methods="get")
     */
    public function downloadActivityFile(Request $insecureRequest)
    {
        if($this->cleanXSS($insecureRequest)
        ) return $this->response;

        // recover all data's request
        $this->dataRequest = $this->requestParameters->getData($this->request);
        if(!isset($this->dataRequest['id'])) return $this->notFoundResponse();

        if($this->getEntities(ActivityFile::class, ['id'])) return $this->response;
        $activity = $this->dataResponse[0];

        if($this->getFile($this->dataResponse[0])) return $this->response;

        $response = new BinaryFileResponse($this->dataResponse[0]);
        $response->headers->set('Content-Type', $activity->getFileType());

        $disposition = HeaderUtils::makeDisposition(
            HeaderUtils::DISPOSITION_ATTACHMENT,
            $activity->getFilePath()
        );
        $response->headers->set('Content-Disposition', $disposition);

        return $response;
   //     return $this->streamFileResponse($this->dataResponse[0], $filename);
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
