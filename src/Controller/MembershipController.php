<?php

namespace App\Controller;

use App\Entity\Organization;
use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class MembershipController
 * @package App\Controller
 * @Route("/member", name="member")
 */
class MembershipController extends CommonController
{
    /**
     * @param Request $insecureRequest
     * @return Response
     * @Route("/add", name="_addMember", methods="put")
     */
    public function addMember(Request $insecureRequest) :Response {
        //cleanXSS
        if($this->cleanXSS($insecureRequest)
        ) return $this->response;

        // recover all data's request
        $this->dataRequest = $this->requestParameters->getData($this->request);
        if(!$this->dataRequest["orgId"] || (!($this->dataRequest["email"]) || !($this->dataRequest["userId"]))){
            return $this->notFoundResponse();
        }
        $this->dataRequest = array_merge($this->dataRequest, ["referent" => $this->getUser()->getId()]);


        $this->dataRequest["id"] = $this->dataRequest['orgId'];
        //Validate fields
        if ($this->isInvalid(
            ["id"],
            null,
            Organization::class)
        ) return $this->response;

        //get query organization object by organization's id && referent's id
        if ($this->getEntities(Organization::class, ["id", "referent"])) return $this->response;
        if(empty($this->dataResponse)){
            $this->notFoundResponse();
        }
        $org = $this->dataResponse[0];
        unset($this->dataRequest["orgId"]);

        //Validate fields
        if ($this->isInvalid(
            ["email"],
            null,
            User::class)
        ) return $this->response;

        //get query organization object by organization's id && referent's id
        if ($this->getEntities(User::class, ["email"])) return $this->response;
        if(empty($this->dataResponse)){
            $this->notFoundResponse();
        }
        $org->addMembership($this->dataResponse[0]);

        if($this->updateEntity($org)) return $this->response;

        //todo rework logging in CommonCOntroller
        return $this->successResponse();
    }

    /**
     * @param Request $insecureRequest
     * @return Response|null
     * @Route("/remove", name="_delete", methods="put")
     */
    public function removeMember(Request $insecureRequest){
        //cleanXSS
        if($this->cleanXSS($insecureRequest)
        ) return $this->response;

        // recover all data's request
        $this->dataRequest = $this->requestParameters->getData($this->request);
        if(!isset($this->dataRequest["orgId"]) || !isset($this->dataRequest["userId"])){
            return $this->notFoundResponse();
        }
        $this->dataRequest = array_merge($this->dataRequest, ["referent" => $this->getUser()->getId()]);


        $this->dataRequest["id"] = $this->dataRequest['orgId'];
        //Validate fields
        if ($this->isInvalid(
            ["id"],
            null,
            Organization::class)
        ) return $this->response;

        //get query organization object by organization's id && referent's id
        if ($this->getEntities(Organization::class, ["id", "referent"])) return $this->response;
        if(!empty($this->dataResponse)){
            $org = $this->dataResponse[0];
            unset($this->dataRequest["orgId"]);
        }else {
            $this->notFoundResponse();
        }

        //Validate fields
        $this->dataRequest["id"] = $this->dataRequest['userId'];
        if ($this->isInvalid(
            ["id"],
            null,
            User::class)
        ) return $this->response;

        //get query organization object by organization's id && referent's id
        if ($this->getEntities(User::class, ["id"])) return $this->response;
        if(!empty($this->dataResponse)){
            $member = $this->dataResponse[0];
        }else {
            $this->notFoundResponse();
        }

        $org->removeMembership($member);

        if($this->updateEntity($org)) return $this->response;

        //todo rework logging
        return $this->successResponse();
    }
}
