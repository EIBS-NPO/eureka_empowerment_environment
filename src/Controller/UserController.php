<?php

namespace App\Controller;

use App\Entity\User;
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

        //create user object && set validated fields
        $user = $this->makeNewEntity(
            ["email", "firstname", "lastname", "password", "phone", "mobile"],
            User::class
        );

        //return potential violations
        if(isset($this->response)) return $this->response;

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

    //todo particular methods for email and password => need refresh token
}
