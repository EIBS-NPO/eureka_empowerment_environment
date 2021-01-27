<?php

namespace App\Controller;

use App\Entity\Organization;
use App\Entity\Project;
use DateTime;
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
     * @param Request $request
     * @return Response
     * @throws \Exception
     */
    public function create(Request $insecureRequest): Response
    {
        //cleanXSS
        if($this->cleanXSS($insecureRequest)
        ) return $this->response;

        // recover all data's request
        $this->dataRequest = $this->requestParameters->getData($this->request);
        $this->dataRequest = array_merge($this->dataRequest, ["creator" => $this->getUser()]);
        $this->dataRequest = array_merge($this->dataRequest, ["isPublic" => false]);

        //optional link with an organization : validation orgId & convert to Organization object
        if (isset($this->dataRequest['orgId'])) {
            $this->dataRequest["id"] = $this->dataRequest['orgId'];
            //Validate fields
            if($this->checkViolations(
                null,
                ["id"],
                Organization::class)
            ) return $this->response;

            //todo recupe l'org avec id ET check si Id user lié relation member!
            if($this->getEntities(Organization::class, ["id"] )) return $this->response;

            if(!empty($this->dataResponse)){
                $this->dataRequest['organization'] = $this->dataResponse[0];
                unset($this->dataRequest["orgId"]);
            }else {
                $this->notFoundResponse();
            }
        }

        //validate param before
        //faire setter spécifique dans entité project
        if(!isset($this->dataRequest['startDate'])){
            $this->dataRequest['startDate'] = new DateTime ($this->dataRequest["startDate"]);
        }
        else {
            $this->dataRequest['startDate'] = new DateTime("now");
        }

        if(isset($this->dataRequest['endDate'])){
            $this->dataRequest['endDate'] = new DateTime ($this->dataRequest["endDate"]);
        }

        ////create new project object && set organization's validated fields
        $project = $this->makeNewEntity(
            ["creator", "title", "description", "startDate", "endDate", "organization", "isPublic"],
            Project::class
        );

        //return potential violations
        if(isset($this->response)) return $this->response;

        //persist the new project
        if($this->persistEntity($project)) return $this->response;

        //success
        return $this->successResponse();
    }

    /**
     * @Route("", name="_put", methods="put")
     * @param Request $insecureRequest
     * @return Response
     * @throws \Exception
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

        //check if essential criterias are present
        //todo requete isPresent?
        if($this->paramValidator->hasAllCriteria(["id"])) return $this->response;

        //validation project's id
        if($this->checkViolations(
            null,
            ["id"],
            Project::class)
        ) return $this->response;

        //and recover projectObject
        if($this->getEntities(Project::class, ['id', 'creator'])) return $this->response;
        $project = $this->dataResponse[0];

        //validation orgId & convert to Organization object
        if (isset($this->dataRequest['orgId'])) {

            //switch for validation controle (attribute have same name)
            $this->dataRequest['id'] = $this->dataRequest['orgId'];
            //validation project's id
            if($this->checkViolations(
                null,
                ["id"],
                Project::class)
            ) return $this->response;

            if($this->getEntities(Organization::class, ['id'])) return $this->response;
            $this->dataRequest['organization'] = $this->dataResponse[0];
        }

        //persist updated project
        if(!empty($this->dataResponse)){
            //set project's validated fields
            $project = $this->setEntity($project, ["title", "description", "startDate", "endDate", "isPublic", "organization"]);

            //return potential violations
            if(isset($this->response)) return $this->response;

            //persist updated project
            if($this->updateEntity($project)) return $this->response;
        }

        $this->dataResponse = [$project];
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
            if($this->checkViolations(
                null,
                ["id"],
                Project::class)
            ) return $this->response;

            if($this->getEntities(Project::class, ["id", "isPublic"] )) return $this->response;
        }else {
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
            if($this->checkViolations(
                null,
                ["id"],
                Project::class)
            ) return $this->response;

            if($this->getEntities(Project::class, ["id", "creator"] )) return $this->response;
        }else {
            //get all created project by connected user
            if($this->getEntities(Project::class, ["creator"] )) return $this->response;
        }

        //success response
        return $this->successResponse();
    }

    //todo conroller Followed

    //todo request for return projectfollowed by a user,
    // need check isPublic and if user is assigned if the project isn't public
}
