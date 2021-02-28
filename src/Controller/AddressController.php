<?php

namespace App\Controller;

use App\Entity\Address;
use App\Entity\Organization;
use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class AddressController
 * @package App\Controller
 * @Route("/address", name="address")
 */
class AddressController extends CommonController
{
    /**
     * @Route("", name="_post", methods="post")
     * @param Request $insecureRequest
     * @return Response
     */
    public function post(Request $insecureRequest): Response
    {
        //cleanXSS
        if($this->cleanXSS($insecureRequest)) return $this->response;

        // recover all data's request
        $this->dataRequest = $this->requestParameters->getData($this->request);

        //optional link with an organization : validation orgId & convert to Organization object
        if (isset($this->dataRequest['orgId'])) {
            if($this->getLinkedEntity(Organization::class, "orgOwner", 'orgId')
            ) return $this->response;
        }else { // else the new address is for current User
            $this->dataRequest["userId"] = $this->getUser()->getId();
            if($this->getLinkedEntity(User::class, "owner", 'userId')
            ) return $this->response;
        }
        $this->dataRequest['ownerType'] = $this->getClassName($this->dataResponse[0]);

        //dataRequest Validations
        if($this->isInvalid(
            ["address", "country", "zipCode", "ownerType"],
            ["complement", "latitude", "longitude", "owner", "orgOwner"],
            Address::class)
        ) return $this->response;

        //create address object && set validated fields
        $address = $this->setEntity(new Address(), ["address", "country", "zipCode", "complement", "latitude", "longitude", "owner", "orgOwner", "ownerType"]);

        //persist the new address
        if($this->persistEntity($address)) return $this->response;

        //success
        return $this->successResponse();
    }

    /**
     * @param Request $insecureRequest
     * @return Response|null
     * @Route("", name="_update", methods="put")
     */
    public function update(Request $insecureRequest) :Response {
        //cleanXSS
        if($this->cleanXSS($insecureRequest)) return $this->response;

        // recover all data's request
        $this->dataRequest = $this->requestParameters->getData($this->request);

        if(!isset($this->dataRequest['id'])) return $this->BadRequestResponse(["missing parameter : id is required. "]);

        if($this->getEntities(Address::class, ['id'])) return $this->response;
        $address = $this->dataResponse[0];

        $this->isOwner($address);

        //dataRequest Validations
        if($this->isInvalid(
            null,
            ["address", "country", "zipCode","complement", "latitude", "longitude", "owner", "orgOwner"],
            Address::class)
        ) return $this->response;


        //set project's validated fields
        $address = $this->setEntity($address, ["address", "country", "zipCode","complement", "latitude", "longitude"]);

        //persist updated project
        if($this->updateEntity($address)) return $this->response;

        //final response
        return $this->successResponse();
    }


    /**
     * @param Request $insecureRequest
     * @return Response
     * @Route("", name="_get", methods="get")
     */
    public function getAddress(Request $insecureRequest) :Response {
        //cleanXSS
        if($this->cleanXSS($insecureRequest)
        ) return $this->response;

        // recover all data's request
        $this->dataRequest = $this->requestParameters->getData($this->request);

        $crit = null;
        if(isset($this->dataRequest['id'])) {
            $crit = $this->dataRequest['id'];
        }

        if($this->getEntities(Address::class, [$crit])) return $this->response;

        return $this->successResponse();
    }


    /**
     * @param Request $insecureRequest
     * @return Response
     * @Route("", name="_delete", methods="delete")
     */
    public function remove(Request $insecureRequest){
        //cleanXSS
        if($this->cleanXSS($insecureRequest)
        ) return $this->response;

        // recover all data's request
        $this->dataRequest = $this->requestParameters->getData($this->request);

        if(!isset($this->dataRequest['id'])) return $this->BadRequestResponse(["missing parameter : id is required. "]);

        if($this->getEntities(Address::class, ['id'])) return $this->response;

        $this->isOwner($this->dataResponse[0]);

        if($this->deleteEntity($this->dataResponse[0])) return $this->response;

        return $this->successResponse();
    }




    /**
     * @param Address $address
     * @return bool|Response
     */
    public function isOwner(Address $address){
        if(
            ($address->getOwner() !== null && $address->getOwner()->getId() !== $this->getUser()->getId())
            || ($address->getOrgOwner() !== null && $address->getOrgOwner()->getReferent()->getId() !== $this->getUser()->getId())
        ){
            return $this->unauthorizedResponse("not your own");
        }
        return true;
    }
}
