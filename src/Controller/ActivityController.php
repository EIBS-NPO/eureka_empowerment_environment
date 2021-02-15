<?php

namespace App\Controller;

use App\Entity\Activity;
use App\Entity\Organization;
use App\Entity\Project;
use DateTime;
use Exception;
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
            ["title", "description", "summary", "postDate", "isPublic", "creator"],
            ["organization", "project"],
            Activity::class)
        ) return $this->response;

        //create Activity object && set validated fields
        $activity = $this->setEntity(new Activity(), ["title", "description", "summary", "postDate", "isPublic", "creator", "organization", "project"]);

        //persist the new activity
        if($this->persistEntity($activity)) return $this->response;

        //success response
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
        if(isset($this->dataRequest["startDate"])){
            $this->dataRequest["startDate"] = new DateTime($this->dataRequest["startDate"]);
        }

        if(isset($this->dataRequest["endDate"])){
            $this->dataRequest["endDate"] = new DateTime($this->dataRequest["endDate"]);
        }

        //validate id and recover activityObject with currentUser id (creator)
        if($this->getEntities(Activity::class, ['id', 'creator'])) return $this->response;
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
                ["title", "description", "summary", "isPublic", "organization"],
                Organization::class)
            ) return $this->response;

            //set project's validated fields
            $project = $this->setEntity($project, ["title", "description", "summary", "isPublic", "organization"]);

            //persist updated project
            if($this->updateEntity($project)) return $this->response;
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
        $this->dataRequest = array_merge($this->dataRequest, ["creator" => $this->getUser()->getId()]);

        //todo controle with assert for image and configLimite (dans pictureHandler,( ou fileHandler)
        /*if(!isset($this->dataRequest["picture"])) {

        }*/

        //get query
        if($this->getEntities(Activity::class, ['id', 'creator'])) return $this->response;
        $activity = $this->dataResponse[0];

        //todo gestion des files dans la requête
        // todo content-type getResponse()->setContentType('image/jpeg')

        if($this->uploadPicture($activity, $this->dataRequest['image'])) return $this->response;

        if($this->isInvalid(
            null,
            ["picturePath"],
            Project::class)
        ) return $this->response;

        //set project's validated fields
        $project = $this->setEntity($activity, ["picturePath"]);

        //persist updated project
        if($this->updateEntity($activity)) return $this->response;

        //download picture
        $this->dataResponse = [$this->loadPicture($activity)];

        //final response
        return $this->successResponse();
    }




    /**
     * returns all public activities
     * @Route("/public", name="_get_public", methods="get")
     * @param Request $insecureRequest
     * @return Response
     */
    public function getPublicActivities(Request $insecureRequest): Response {
        //cleanXSS
        if($this->cleanXSS($insecureRequest)
        ) return $this->response;

        // recover all data's request
        $this->dataRequest = $this->requestParameters->getData($this->request);
        $this->dataRequest["isPublic"] = true;

        //todo switch sur dataRequest['context'] => 'public' || 'creator' || 'assigned'
        //todo for dispatch the good request by access rules.

        //get one public project
        if(isset($this->dataRequest["id"])){
            if($this->getEntities(Activity::class, ["id", "isPublic"] )) return $this->response;
        } else {
            //get all public project
            if($this->getEntities(Activity::class, ["isPublic"] )) return $this->response;
        }

        //download picture
        foreach($this->dataResponse as $key => $activity){
            $this->dataResponse[$key] = $this->loadPicture($activity);
        }

        //success response
        return $this->successResponse();
    }

    /**
     * returns to a user his created projects
     * @Route("", name="_get", methods="get")
     * @param Request $insecureRequest
     * @return Response
     */
    public function getActivities(Request $insecureRequest): Response {
        //cleanXSS
        if($this->cleanXSS($insecureRequest)
        ) return $this->response;

        // recover all data's request
        $this->dataRequest = $this->requestParameters->getData($this->request);

        //todo maybe change assigned context, 'cause it'snt created for now
        //todo can be placed une CommonController
        $criterias = [];
        switch($this->dataRequest['context']){
            case 'assigned':
                $this->dataRequest["assigned"] = $this->getUser()->getId();
                $criterias[]='assigned';
                break;
            case 'creator':
                $this->dataRequest["creator"] = $this->getUser()->getId();
                $criterias[]='creator';
                break;
            default:
                $this->dataRequest["isPublic"] = true;
                $criterias[]="isPublic";
        }

        //if query for only one
        if(isset($this->dataRequest["id"])) {
            $criterias[] = 'id';
        }

        if($this->getEntities(Activity::class, $criterias )) return $this->response;

        //download picture
        foreach($this->dataResponse as $key => $activity){
            $this->dataResponse[$key] = $this->loadPicture($activity);
        }

        //success response
        return $this->successResponse();
    }
}
