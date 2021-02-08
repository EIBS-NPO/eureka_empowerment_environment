<?php

namespace App\Controller;

use App\Entity\ActivityFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
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
        /*$file = new ActivityFile();
        $file->setTitle("fileTest")
            ->setDescription("description text")
            ->setPostDate(New \DateTime("now"))
            ->setSummary("chapitre 1 : test1, chapitre 2 : test2")
            ->setIsPublic(true)
            ->setCreator($this->getUser())
            ->setFilePath("filePath/path")
            ->setFileType("txt");*/

        //cleanXSS
        if($this->cleanXSS($insecureRequest)
        ) return $this->response;

        // recover all data's request
        $this->dataRequest = $this->requestParameters->getData($this->request);
        $this->dataRequest = array_merge($this->dataRequest, ["creator" => $this->getUser()]);
        $this->dataRequest = array_merge($this->dataRequest, ["postDate" => New \DateTime("now")]);

        //todo add address optional

        //create Organization object && set validated fields
        $file = $this->makeNewEntity(
            ["title", "description", "Summary", "postDate", "isPublic", "creator", "filePath", "fileType"],
            ActivityFile::class
        );

        //return potential violations
        if(isset($this->response)) return $this->response;

        //persist the new activityFile
        if($this->persistEntity($file)) return $this->response;

        //success response
        return $this->successResponse();
    }
}
