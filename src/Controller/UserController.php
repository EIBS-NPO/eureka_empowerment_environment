<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\FileHandler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

/**
 * Class UserController
 * @package App\Controller
 * @Route("/user", name="user")
 */
class UserController extends CommonController
{
    /**
     * @Route("/register", name="_registration", methods="post")
     * @param Request $insecureRequest
     * @param UserPasswordEncoderInterface $encoder
     * @return Response
     */
    public function register(Request $insecureRequest, UserPasswordEncoderInterface $encoder): Response
    {
        //cleanXSS
        if($this->cleanXSS($insecureRequest)
        ) return $this->response;

       // recover all data's request
        $this->dataRequest = $this->requestParameters->getData($this->request);
        //dataRequest Validations
        if($this->isInvalid(
            ["email", "firstname", "lastname", "password"],
            ["phone", "mobile", "picturePath"],
            User::class)
        ) return $this->response;

        //create user object && set validated fields
        $user = $this->setEntity(new User(),["email", "firstname", "lastname", "password", "phone", "mobile", "picturePath"]);

        //hash password
        $hash = $encoder->encodePassword($user, $user->getPassword());
        $user->setPassword($hash);
        //initiate role USER
        $user->setRoles(["ROLE_USER"]);

        //persist the new user
        if($this->persistEntity($user)) return $this->response;

        //final response
        return $this->successResponse();
    }

    /**
     * @Route("", name="_get", methods="get")
     * @param Request $insecureRequest
     * @return Response
     */
    public function getUserProfile(Request $insecureRequest): Response
    {
        //cleanXSS
        if($this->cleanXSS($insecureRequest)
        ) return $this->response;

        // recover all data's request
        $this->dataRequest = $this->requestParameters->getData($this->request);
        $this->dataRequest = array_merge($this->dataRequest, ["id" => $this->getUser()->getId()]);

        //get request
        if($this->getEntities(User::class, ["id"])) return $this->response;

        //download picture
        $this->dataResponse = [$this->loadPicture($this->dataResponse[0])];

        //final response
        return $this->successResponse();
    }

    /**
     * @Route("", name="_update", methods="put")
     * @param Request $insecureRequest
     * @return Response
     */
    public function updateUser(Request $insecureRequest) : Response
    {
        //cleanXSS
        if($this->cleanXSS($insecureRequest)
        ) return $this->response;

        // recover all data's request
        $this->dataRequest = $this->requestParameters->getData($this->request);
        $this->dataRequest = array_merge($this->dataRequest, ["id" => $this->getUser()->getId()]);

        //get query
        if($this->getEntities(User::class, ["id"] )) return $this->response;
        if(!empty($this->dataResponse)) {
            if($this->isInvalid(
                null,
                ["firstname", "lastname", "phone", "mobile"],
                User::class)
            ) return $this->response;

            //set user's validated fields
            $user = $this->setEntity($this->dataResponse[0], ["firstname", "lastname", "phone", "mobile"]);

            //return potential violations
            if(isset($this->response)) return $this->response;

            //persist updated user
            if ($this->updateEntity($user)) return $this->response;
        }

        //final response
        return $this->successResponse();
    }

    //todo if user have already apicture, delete the old pic

    /**
     * @param Request $insecureRequest
     * @return Response
     * @Route("/picture", name="_picture_put", methods="post")
     */
    public function putPicture(Request $insecureRequest ) :Response {

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
        //if($this->uploadPicture($user, $insecureRequest->files->get('image'))) return $this->response;
        if($this->uploadPicture($user, $this->dataRequest['image'])) return $this->response;
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

    /**
     * @deprecated
     * @param Request $insecureRequest
     * @return Response
     * @Route("/picture", name="_picture_get", methods="get")
     */
    /*public function getPicProfil(Request $insecureRequest) :Response
    {
        //cleanXSS
        if($this->cleanXSS($insecureRequest)
        ) return $this->response;

        // recover all data's request
        $this->dataRequest = $this->requestParameters->getData($this->request);
        $this->dataRequest = array_merge($this->dataRequest, ["id" => $this->getUser()->getId()]);

        $destination =  $this->getParameter('kernel.project_dir').'/src/uploads/pictures/';
        $path = $destination . $this->dataRequest["pic"];
     //   $path2 = $destination .'/'. $this->dataRequest["pic"] .".png";

        if (file_exists($path)){

            $img = file_get_contents($path);
            $img = base64_encode($img);

            $this->dataResponse = [$img];
        }else{
            return $this->notFoundResponse();
        }
        return $this->successResponse();
    }*/

    //todo particular methods for email and password => need refresh token
    public function resetPassword(){}
    public function changeEmail(){}





















}
