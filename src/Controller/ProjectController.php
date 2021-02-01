<?php

namespace App\Controller;

use App\Entity\Organization;
use App\Entity\Project;
use DateTime;
use Exception;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class ProjectController
 * @package App\Controller
 * @Route("/project", name="project")
 */
class ProjectController extends CommonController
{
    //todo access role?
    /**
     * @Route("", name="_post", methods="post")
     * @param Request $insecureRequest
     * @return Response
     * @throws Exception
     */
    public function create(Request $insecureRequest): Response
    {
        //cleanXSS
        if($this->cleanXSS($insecureRequest)
        ) return $this->response;

        // recover all data's request
        $this->dataRequest = $this->requestParameters->getData($this->request);
        // add default required values && convert datetime
        $this->dataRequest = array_merge($this->dataRequest, ["creator" => $this->getUser()]);
        if(!isset($this->dataRequest["isPublic"])){
            $this->dataRequest = array_merge($this->dataRequest, ["isPublic" => false]);
        }
        if(isset($this->dataRequest['startDate'])){
            $this->dataRequest['startDate'] = new DateTime ($this->dataRequest["startDate"]);
        }
        else {
            $this->dataRequest = array_merge($this->dataRequest, ['startDate'=> new DateTime("now")]);
        }
        if(isset($this->dataRequest['endDate'])){
            $this->dataRequest['endDate'] = new DateTime ($this->dataRequest["endDate"]);
        }

        //optional link with an organization : validation orgId & convert to Organization object
        if (isset($this->dataRequest['orgId'])) {
            if($this->getLinkedEntity(Organization::class, "organization", 'orgId')
            ) return $this->response;
        }

        //dataRequest Validations
        if($this->isInvalid(
            ["creator", "title", "description", "startDate", "isPublic"],
            ["endDate", "organization"],
            Project::class)
        ) return $this->response;

        //create Activity object && set validated fields
        $project = $this->setEntity(new Project(), ["creator", "title", "description", "startDate", "endDate", "organization", "isPublic"]);

        //persist the new project
        if($this->persistEntity($project)) return $this->response;

        //success
        return $this->successResponse();
    }

    /**
     * @Route("", name="_put", methods="put")
     * @param Request $insecureRequest
     * @return Response
     * @throws Exception
     */
    public function updateProject (Request $insecureRequest) :Response
    {
        //cleanXSS
        if($this->cleanXSS($insecureRequest)
        ) return $this->response;

        // recover all data's request
        $this->dataRequest = $this->requestParameters->getData($this->request);
        $this->dataRequest = array_merge($this->dataRequest, ["creator" => $this->getUser()->getId()]);
        if(!isset($this->dataRequest["startDate"])){
            $this->dataRequest["startDate"] = new DateTime("now");
        }else {
            $this->dataRequest["startDate"] = new DateTime($this->dataRequest["startDate"]);
        }

        if(isset($this->dataRequest["endDate"])){
            $this->dataRequest["endDate"] = new DateTime($this->dataRequest["endDate"]);
        }

        //validate id and recover projectObject with currentUser id (creator)
        if($this->getEntities(Project::class, ['id', 'creator'])) return $this->response;
        $project = $this->dataResponse[0];

        //potential validation orgId & convert to Organization object
        if (isset($this->dataRequest['orgId'])) {
            if($this->getLinkedEntity(Organization::class, "organization", 'orgId')
            ) return $this->response;
        }

        //persist updated project
        if(!empty($this->dataResponse)){

            if($this->isInvalid(
                null,
                ["title", "description", "startDate", "endDate", "isPublic", "organization"],
                Organization::class)
            ) return $this->response;

            //set project's validated fields
            $project = $this->setEntity($project, ["title", "description", "startDate", "endDate", "isPublic", "organization"]);

            //persist updated project
            if($this->updateEntity($project)) return $this->response;
        }
        //todo check, useless, now udpate, do this
        //$this->dataResponse = [$project];

        //final response
        return $this->successResponse();
    }

    /**
     * returns all public projects
     * @Route("/public", name="_get_public", methods="get")
     * @param Request $insecureRequest
     * @return Response
     */
    public function getProjects(Request $insecureRequest): Response {
        //cleanXSS
        if($this->cleanXSS($insecureRequest)
        ) return $this->response;

        // recover all data's request
        $this->dataRequest = $this->requestParameters->getData($this->request);
        $this->dataRequest["isPublic"] = true;

        //get one public project
        if(isset($this->dataRequest["id"])){
            if($this->getEntities(Project::class, ["id", "isPublic"] )) return $this->response;
        } else {
            //get all public project
            if($this->getEntities(Project::class, ["isPublic"] )) return $this->response;
        }

        //success response
        return $this->successResponse();
    }

    /**
     * returns to a user his created projects
     * @Route("/created", name="_get_created", methods="get")
     * @param Request $insecureRequest
     * @return Response
     */
    public function getProjectsCreated(Request $insecureRequest): Response {
        //cleanXSS
        if($this->cleanXSS($insecureRequest)
        ) return $this->response;

        // recover all data's request
        $this->dataRequest = $this->requestParameters->getData($this->request);
        $this->dataRequest["creator"] = $this->getUser()->getId();

        //get one created project by connected user
        if(isset($this->dataRequest["id"])){
            if($this->getEntities(Project::class, ["id", "creator"] )) return $this->response;
        }else {
            //get all created project by connected user
            if($this->getEntities(Project::class, ["creator"] )) return $this->response;
        }

        //success response
        return $this->successResponse();
    }

    //todo controller Followed

    //todo request for return projectfollowed by a user,
    // need check isPublic and if user is assigned if the project isn't public
}
