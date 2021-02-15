<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class UserController
 * @package App\Controller
 * @Route("/file", name="file")
 */
class FileController extends CommonController
{
    /**
     * @param Request $insecureRequest
     * @return Response
     * @Route("/picture", name="_picture_put", methods="put")
     */
    public function putPicture(Request $insecureRequest ) :Response {

        //todo recevoir le type de la class par queryparams ?
        //cleanXSS
        if($this->cleanXSS($insecureRequest)
        ) return $this->response;

        // recover all data's request
        $this->dataRequest = $this->requestParameters->getData($this->request);
        $this->dataRequest = array_merge($this->dataRequest, ["id" => $this->getUser()->getId()]);

        //todo controle with assert for image and configLimite (dans pictureHandler,( ou fileHandler)
        /*if(!isset($this->dataRequest["picture"])) {

        }*/

        //get query
        if($this->getEntities(User::class, ["id"] )) return $this->response;
        $user = $this->dataResponse[0];

        //todo gestion des files dans la requête
        // todo content-type getResponse()->setContentType('image/jpeg')
        if($this->uploadPicture($user, $insecureRequest->files->get('image'))) return $this->response;
        //$this->dataRequest["picturePath"] = $picUploader->upload($user, $insecureRequest->files->get('image'));

        if($this->isInvalid(
            null,
            ["picturePath"],
            User::class)
        ) return $this->response;

        //set project's validated fields
        $user = $this->setEntity($user, ["picturePath"]);

        //persist updated project
        if($this->updateEntity($user)) return $this->response;

        //download picture
        $this->dataResponse = [$this->loadPicture($user)];

        //final response
        return $this->successResponse();
    }
}
