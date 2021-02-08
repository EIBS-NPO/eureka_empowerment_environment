<?php

namespace App\Controller;

use App\Entity\Activity;
use App\Entity\Organization;
use App\Entity\Project;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class ActivityController
 * @package App\Controller
 * @Route("activity", name="activity")
 */
class ActivityController extends CommonController
{
    /**
     * @Route("", name="_post", methods="post")
     * @param Request $insecureRequest
     * @return Response
     */
    public function postFile(Request $insecureRequest): Response
    {
        //cleanXSS
        if($this->cleanXSS($insecureRequest)
        ) return $this->response;

        // recover all data's request
        $this->dataRequest = $this->requestParameters->getData($this->request);
        $this->dataRequest = array_merge($this->dataRequest, ["creator" => $this->getUser()]);
        $this->dataRequest = array_merge($this->dataRequest, ["postDate" => New \DateTime("now")]);

        //optional link with an organization : validation orgId & convert to Organization object
        if (isset($this->dataRequest['orgId'])) {
            if($this->getLinkedEntity(Organization::class, "organization", 'orgId')
            ) return $this->response;
        }

        //optional link with a project : validation projectId & convert to Project object
        //todo if an orgLink existing, check if the project is linker with this org?
        //need reflexion for logic Metier (oups franGlais)
        if (isset($this->dataRequest['projectId'])) {
            if($this->getLinkedEntity(Project::class, "project", 'projectId')
            ) return $this->response;
        }

        //dataRequest Validations
        if($this->isInvalid(
            ["title", "description", "Summary", "postDate", "isPublic", "creator"],
            ["organization", "project"],
            Activity::class)
        ) return $this->response;

        //create Activity object && set validated fields
        $activity = $this->setEntity(new Activity(), ["title", "description", "Summary", "postDate", "isPublic", "creator", "organization", "project"]);

        //persist the new activity
        if($this->persistEntity($activity)) return $this->response;

        //success response
        return $this->successResponse();
    }
}
