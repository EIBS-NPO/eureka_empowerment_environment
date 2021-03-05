<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\FileHandler;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
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
     * @param Request $request
     * @param UserPasswordEncoderInterface $encoder
     * @return Response
     */
    public function register(Request $request, UserPasswordEncoderInterface $encoder): Response
    {
        //cleanXSS
        if($this->cleanXSS($request)
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
     * @param Request $request
     * @return Response
     */
    public function getUsers(Request $request): Response
    {
        //cleanXSS
        if($this->cleanXSS($request)
        ) return $this->response;

        // recover all data's request
        $this->dataRequest = $this->requestParameters->getData($this->request);

        $criterias = [];
        if(!isset($this->dataRequest["all"])){
            if (!isset($this->dataRequest["id"])){
                //default query for current user
                $this->dataRequest["id"] = $this->getUser()->getId();
            }
            $criterias[] =  "id";
        }
        //get request
        if($this->getEntities(User::class, $criterias)) return $this->response;

        //download picture
        foreach($this->dataResponse as $key => $user){
            $this->dataResponse[$key] = $this->loadPicture($user);
        }


        //final response
        return $this->successResponse();
    }

    /**
     * @Route("", name="_update", methods="put")
     * @param Request $request
     * @return Response
     */
    public function updateUser(Request $request) : Response
    {
        //cleanXSS
        if($this->cleanXSS($request)) return $this->response;

        // recover all data's request
        $this->dataRequest = $this->requestParameters->getData($this->request);
   //     $this->dataRequest["id"] = $this->getUser()->getId();

        if($this->dataRequest['id'] !== $this->getUser()->getId()) {
            if ($this->getUser()->getRoles()[0] !== "ROLE_ADMIN") {
                return $this->unauthorizedResponse("unauthorized access");
            }
        }

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

            $this->dataResponse = [$this->loadPicture($user)];
        }

        //final response
        return $this->successResponse();
    }

    //todo if user have already apicture, delete the old pic

    /**
     * @param Request $request
     * @return Response
     * @Route("/picture", name="_picture_put", methods="post")
     */
    public function putPicture(Request $request ) :Response {

        //cleanXSS
        if($this->cleanXSS($request)
        ) return $this->response;

        // recover all data's request
        $this->dataRequest = $this->requestParameters->getData($this->request);
        $this->dataRequest["id"] = $this->getUser()->getId();

        //todo controle with assert for image and configLimite (dans pictureHandler,( ou fileHandler)
        /*if(!isset($this->dataRequest["picture"])) {

        }*/

        //get query
        if($this->getEntities(User::class, ["id"] )) return $this->response;
        $user = $this->dataResponse[0];

        //todo gestion des files dans la requête
        // todo content-type getResponse()->setContentType('image/jpeg')
        //if($this->uploadPicture($user, $request->files->get('image'))) return $this->response;
        if($this->uploadPicture($user, $this->dataRequest['image'])) return $this->response;
        //$this->dataRequest["picturePath"] = $picUploader->upload($user, $request->files->get('image'));

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
     * @param Request $request
     * @return Response
     * @Route("/picture", name="_picture_get", methods="get")
     */
    /*public function getPicProfil(Request $request) :Response
    {
        //cleanXSS
        if($this->cleanXSS($request)
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

    /**
     * @param Request $request
     * @param UserPasswordEncoderInterface $encoder
     * @return Response|null
     * @Route("/password", name="_password", methods="post")
     */
    public function resetPassword(Request $request, UserPasswordEncoderInterface $encoder)
    {
        //cleanXSS
        if ($this->cleanXSS($request)
        ) return $this->response;

        // recover all data's request
        $this->dataRequest = $this->requestParameters->getData($this->request);

    //    dd($this->dataRequest);

        if (!isset($this->dataRequest['password'])
            || !isset($this->dataRequest['newPassword'])
            || !isset($this->dataRequest['confirmNewPassword'])
        ) return $this->BadRequestResponse(["missing parameter : password, newPassword and confirm are required. "]);

        if($this->dataRequest['newPassword'] !== $this->dataRequest['confirmNewPassword']) {
            return $this->BadRequestResponse(["newPassword"=>"not match", "confirmNewPassword"=>"not match"]);
        }

        $this->dataRequest["id"] = $this->getUser()->getId();

        if ($this->getEntities(User::class, ["id"])) return $this->response;
        if (!empty($this->dataResponse)) {
            $user = $this->dataResponse[0];

            $hash = $encoder->encodePassword($user, $this->dataRequest['password']);
            if(!$encoder->isPasswordValid($user, $this->dataRequest['password'])){ return $this->BadRequestResponse(["password"=> "wrong"]);}

            $this->dataRequest['password'] =  $this->dataRequest['newPassword'];
            if ($this->isInvalid(
                null,
                ["password"],
                User::class)
            ) return $this->response;

            $hash = $encoder->encodePassword($user, $this->dataRequest['password']);
            $user->setPassword($hash);

            //persist the new user
            if ($this->persistEntity($this->dataResponse[0])) return $this->response;
        }

        //final response
        return $this->successResponse();
    }


    /**
     * @Route("/email", name="_reset_email", methods="put")
     * @param Request $request
     * @param JWTTokenManagerInterface $JWTManager
     * @return Response
     */
    public function changeEmail(Request  $request, JWTTokenManagerInterface $JWTManager){
        //cleanXSS
        if ($this->cleanXSS($request)
        ) return $this->response;

        // recover all data's request
        $this->dataRequest = $this->requestParameters->getData($this->request);

        if(isset($this->dataRequest['userId'])) {
            if ($this->getUser()->getRoles()[0] !== "ROLE_ADMIN") {
                return $this->unauthorizedResponse("unauthorized access");
            } else {
                $this->dataRequest["id"] = $this->dataRequest['userId'];
            }
        }else {
            $this->dataRequest["id"] = $this->getUser()->getId();
        }

        if ($this->getEntities(User::class, ["id"])) return $this->response;
        $user = $this->dataResponse[0];

        //check required params
        if(!$this->hasAllCriteria(["email"])) return $this->response;

        $user->setEmail($this->dataRequest['email']);

        $this->updateEntity($user);

        if($this->dataRequest["userId"] === $this->getUser()->getId()){
            $this->dataResponse = [
                $user,
                'token' => $JWTManager->create($user),
            ];
        }else {
            $this->dataResponse = [$user];
        }
        return $this->successResponse();
    }

    /**
     * @param Request $request
     * @return Response
     * @Route("/activ", name="_activation", methods="put")
     */
    public function activation(Request $request){
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        //cleanXSS
        if ($this->cleanXSS($request)
        ) return $this->response;

        // recover all data's request
        $this->dataRequest = $this->requestParameters->getData($this->request);

        if ($this->getUser()->getRoles()[0] !== "ROLE_ADMIN") {
            return $this->unauthorizedResponse("unauthorized access");
        }

        $this->dataRequest["id"] = $this->dataRequest['userId'];
        if ($this->getEntities(User::class, ["id"])) return $this->response;
        $user = $this->dataResponse[0];

        if($user->getRoles()[0] === "ROLE_USER"){
            $user->setRoles(["ROLE_DISABLE"]);
        }
        else {$user->setRoles(["ROLE_USER"]);}

        if($this->updateEntity($user)) return $this->response;

        $this->dataResponse = [$user->getRoles()[0]];
        return $this->successResponse();
    }





















}
