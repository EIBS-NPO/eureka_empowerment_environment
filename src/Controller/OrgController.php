<?php

namespace App\Controller;

use App\Entity\Organization;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class OrgController
 * @package App\Controller
 * @Route("/org", name="org")
 */
class OrgController extends CommonController
{
    /**
     * @Route("", name="_registration", methods="post")
     * @param Request $insecureRequest
     * @return Response
     */
    public function create(Request $insecureRequest) :Response
    {
        //cleanXSS
        if($this->cleanXSS($insecureRequest)
        ) return $this->response;

        // recover all data's request
        $this->dataRequest = $this->requestParameters->getData($this->request);
        $this->dataRequest = array_merge($this->dataRequest, ["referent" => $this->getUser()]);

        //todo add address optional

        //create Organization object && set validated fields
        $org = $this->makeNewEntity(
            ["referent", "type", "name", "email", "phone"],
            Organization::class
        );

        //return potential violations
        if(isset($this->response)) return $this->response;

        //persist the new organization
        if($this->persistEntity($org)) return $this->response;

        //success response
        return $this->successResponse();
    }

    /**
     * @Route("/public", name="_get_public", methods="get")
     * @param Request $insecureRequest
     * @return Response
     */
    public function getPublicOrganization(Request $insecureRequest) :Response
    {
        //cleanXSS
        if($this->cleanXSS($insecureRequest)
        ) return $this->response;

        // recover all data's request
        $this->dataRequest = $this->requestParameters->getData($this->request);

        //Validate fields
        if($this->checkViolations(
            null,
            ["id"],
            Organization::class)
        ) return $this->response;

        //get query
        if($this->getEntities(Organization::class, ["id"] )) return $this->response;

        //success response
        return $this->successResponse();
    }

    /**
     * @Route("", name="_get", methods="get")
     * @param Request $insecureRequest
     * @return Response|null
     */
    public function getOrganization(Request $insecureRequest){
        //cleanXSS
        if($this->cleanXSS($insecureRequest)
        ) return $this->response;

        // recover all data's request
        $this->dataRequest = $this->requestParameters->getData($this->request);
        $this->dataRequest["referent"] = $this->getUser()->getId();

        //Validate fields
        if(isset($this->dataRequest['id'])){
            if($this->checkViolations(
                null,
                ["id"],
                Organization::class)
            ) return $this->response;

            if($this->getEntities(Organization::class, ["id", "referent"] )) return $this->response;
        }else {
            if($this->getEntities(Organization::class, ["referent"] )) return $this->response;
        }

        //success response
        return $this->successResponse();
    }

    //todo ajout logo update

    /**
     * @param Request $insecureRequest
     * @return Response
     * @ROUTE("", name="_put", methods="put")
     */
    public function updateOrganization(Request $insecureRequest) :Response
    {
        //cleanXSS
        if($this->cleanXSS($insecureRequest)
        ) return $this->response;

        // recover all data's request
        $this->dataRequest = $this->requestParameters->getData($this->request);
        $this->dataRequest = array_merge($this->dataRequest, ["referent" => $this->getUser()->getId()]);

        //Validate id fields
        if($this->checkViolations(
            ["id"],
            null,
            Organization::class)
        ) return $this->response;

        //todo check orgAccess

        //get query organization object by organization's id && referent's id
        if($this->getEntities(Organization::class, ["id", "referent"] )) return $this->response;

        if(!empty($this->dataResponse)){
            //set organization's validated fields
            $org = $this->setEntity($this->dataResponse[0], ["type", "name", "email", "phone"]);

            //return potential violations
            if(isset($this->response)) return $this->response;

            //persist updated org
            if($this->updateEntity($org)) return $this->response;
        }

        //final response
        return $this->successResponse();
    }
}
