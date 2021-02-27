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

        //dataRequest Validations
        if($this->isInvalid(
            ["name", "type", "email", "referent"],
            ["phone", 'description'],
            Organization::class)
        ) return $this->response;

        //create user object && set validated fields
        $org = $this->setEntity(new Organization(),["name", "type", "email", "description", "referent", "phone"]);

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

        //get query, if id not define, query getALL
        if($this->getEntities(Organization::class, ["id"] )) return $this->response;

        //download picture
        foreach($this->dataResponse as $key => $org){
            $this->dataResponse[$key] = $this->loadPicture($org);
        }

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

        //validation requestParam and recover organization(s)
        if(isset($this->dataRequest['id'])){
            if($this->getEntities(Organization::class, ["id", "referent"] )) return $this->response;
        }else {
            if($this->getEntities(Organization::class, ["referent"] )) return $this->response;
        }

        //download picture
        foreach($this->dataResponse as $key => $org){
            $this->dataResponse[$key] = $this->loadPicture($org);
        }
//dd($this->dataResponse);
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
        //cleanXSS 603771a4a4792_blob.jpeg
        if($this->cleanXSS($insecureRequest)
        ) return $this->response;

        // recover all data's request
        $this->dataRequest = $this->requestParameters->getData($this->request);
        $this->dataRequest = array_merge($this->dataRequest, ["referent" => $this->getUser()->getId()]);

        //validation for paramRequest && get query organization object by organization's id && referent's id
        if($this->getEntities(Organization::class, ["id", "referent"] )) return $this->response;

        if(!empty($this->dataResponse)){
            if($this->isInvalid(
                null,
                ["type", "name", "description", "email", "phone"],
                Organization::class)
            ) return $this->response;

            //set organization's validated fields
            $org = $this->setEntity($this->dataResponse[0], ["type", "name", "description", "email", "phone"]);

            //persist updated org
            if($this->updateEntity($org)) return $this->response;
        }

        //final response
        return $this->successResponse();
    }

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
        $this->dataRequest = array_merge($this->dataRequest, ["referent" => $this->getUser()->getId()]);

        //todo controle with assert for image and configLimite (dans pictureHandler,( ou fileHandler)
        /*if(!isset($this->dataRequest["picture"])) {

        }*/

        //get query
        if($this->getEntities(Organization::class, ['id', 'referent'])) return $this->response;
        $org = $this->dataResponse[0];

        //todo gestion des files dans la requête
        // todo content-type getResponse()->setContentType('image/jpeg')

        if($this->uploadPicture($org, $this->dataRequest['image'])) return $this->response;

        if($this->isInvalid(
            null,
            ["picturePath"],
            Organization::class)
        ) return $this->response;

        //set project's validated fields
        $org = $this->setEntity($org, ["picturePath"]);

        //persist updated project
        if($this->updateEntity($org)) return $this->response;

        //download picture
        $this->dataResponse = [$this->loadPicture($org)];

        //final response
        return $this->successResponse();
    }
}
