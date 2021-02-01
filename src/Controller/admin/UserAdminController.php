<?php

namespace App\Controller\admin;

use App\Controller\CommonController;
use App\Entity\User;
use App\Exceptions\SecurityException;
use Exception;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class UserAdminController
 * @package App\Controller
 * @Route("/admin/user", name="admin")
 */
class UserAdminController extends CommonController
{
    /**
     * @Route("", name="_get_user", methods="get")
     * @param Request $insecureRequest
     * @return Response
     */
    public function getUserInfo(Request $insecureRequest): Response
    {
        //cleanXSS
        if($this->cleanXSS($insecureRequest)
        ) return $this->response;

        // recover all data's request
        $this->dataRequest = $this->requestParameters->getData($this->request);

        $criteria = null;
        if(isset($this->dataRequest["email"])){
            if($this->isInvalid(
                null,
                ["email"],
                User::class)
            ) return $this->response;
            $criteria = "email";
        }
        if(isset($this->dataRequest["id"])){
            if($this->isInvalid(
                null,
                ["id"],
                User::class)
            ) return $this->response;
            $criteria = "id";
        }

        if($this->getEntities(User::class, [$criteria] )) return $this->response;

        //success response
        return $this->successResponse();
    }

    /**
     * @Route("", name="_udpate_user", methods="put")
     * @param Request $insecureRequest
     * @return Response
     */
    public function updateUserInfo(Request $insecureRequest) : Response
    {
        //cleanXSS
        if($this->cleanXSS($insecureRequest)
        ) return $this->response;

        // recover all data's request
        $this->dataRequest = $this->requestParameters->getData($this->request);

        if(isset($this->dataRequest["id"])){
            if($this->isInvalid(
                null,
                ["id"],
                User::class)
            ) return $this->response;
        }

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
}
