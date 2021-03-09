<?php

namespace App\Controller;

use App\Entity\Activity;
use App\Entity\ActivityFile;
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
     * @param Request $request
     * @return Response
     */
    public function create(Request $request): Response
    {
        //cleanXSS
        if($this->cleanXSS($request)
        ) return $this->response;

        // recover all data's request
        $this->dataRequest = $this->requestParameters->getData($this->request);
        $this->dataRequest = array_merge($this->dataRequest, ["creator" => $this->getUser()]);
        $this->dataRequest = array_merge($this->dataRequest, ["postDate" => New \DateTime("now")]);
        if(!isset($this->dataRequest['isPublic'])){$this->dataRequest = array_merge($this->dataRequest, ["isPublic" => false]);}

       /* //optional link with an organization : validation orgId & convert to Organization object
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
        }*/

        //dataRequest Validations
        if($this->isInvalid(
            ["title", "description", "summary", "postDate", "creator"],
            [],
            Activity::class)
        ) return $this->response;

        //create Activity object && set validated fields
        $activity = $this->setEntity(new Activity(), ["title", "description", "summary", "postDate", "isPublic", "creator" ]);

        //persist the new activity
        if($this->persistEntity($activity)) return $this->response;

        //success response
        return $this->successResponse();
    }





    /**
     * @param Request $request
     * @return Response
     * @throws Exception
     * @Route("", name="_put", methods="put")
     */
    public function updateActivity (Request $request) :Response
    {
        //cleanXSS
        if($this->cleanXSS($request)
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
    //    dd($this->dataResponse);
        $activity = $this->dataResponse[0];

        //Link or unlink with an org
        /*if(!isset($this->dataRequest['orgId'])){
            $this->dataRequest['organization'] = null;
        }else {
            if($this->getLinkedEntity(Organization::class, "organization", 'orgId')  ) return $this->response;
        }*/

        //Link or unlink with an project
       /* if(!isset($this->dataRequest['projectId'])){
            $this->dataRequest['project'] = null;
        }else {
            if($this->getLinkedEntity(Project::class, "project", 'projectId')  ) return $this->response;
        }*/

        //persist updated project
        if(!empty($this->dataResponse)){

            if($this->isInvalid(
                null,
                ["title", "summary", "isPublic"],
                Activity::class)
            ) return $this->response;

            //set project's validated fields
            $activity = $this->setEntity($activity, ["title", "description", "summary", "isPublic"]);

            //persist updated project
            if($this->updateEntity($activity)) return $this->response;
      //      dd($this->dataResponse);
            $this->getPics($this->dataResponse);
        }

        //final response
        return $this->successResponse("read_activity");
    }




    /**
     * @param Request $request
     * @return Response
     * @Route("/picture", name="_picture_put", methods="post")
     */
    public function putPicture(Request $request ) :Response {

        //cleanXSS
        if($this->cleanXSS($request)
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

        if($this->uploadPicture($activity, $this->dataRequest['image'])) return $this->response;

        if($this->isInvalid(
            null,
            ["picturePath"],
            Project::class)
        ) return $this->response;

        //set project's validated fields
        $activity = $this->setEntity($activity, ["picturePath"]);

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
     * @param Request $request
     * @return Response
     */
    public function getPublicActivities(Request $request): Response {
        //cleanXSS
        if($this->cleanXSS($request)
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
            if($activity->getProject() !== null){
                $activity->setProject($this->loadPicture($activity->getProject()));
            }
            if($activity->getOrganization() !== null){
                $activity->setOrganization($this->loadPicture($activity->getOrganization()));
            }
        }

        //todo controle sur le contenu non public
        //success response
        return $this->successResponse("read_activity");
    }

    //todo puisque heritage, fusionner activityController et activityFile controller?
    /**
     * returns to a user his created projects
     * @Route("", name="_get", methods="get")
     * @param Request $request
     * @return Response
     */
    public function getActivities(Request $request): Response {
        //cleanXSS
        if($this->cleanXSS($request)
        ) return $this->response;

        // recover all data's request
        $this->dataRequest = $this->requestParameters->getData($this->request);

        //todo maybe change assigned context, 'cause it'snt created for now
        //todo can be placed une CommonController
        $criterias = [];
        switch($this->dataRequest['ctx']){
            /*case 'assigned':
                $this->dataRequest["assigned"] = $this->getUser()->getId();
                $criterias[]='assigned';
                break;*/
            case 'creator':
                $this->dataRequest["creator"] = $this->getUser()->getId();
                $criterias[]='creator';
                break;
            case 'org': //todo util?
                $this->dataResponse["organization"] = $this->dataRequest['orgId'];
                $criterias[]='organization';
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
//dd($this->dataResponse);
        //download picture
        foreach($this->dataResponse as $key => $activity){
            $this->dataResponse[$key] = $this->loadPicture($activity);
            if($activity->getProject() !== null){
                $activity->setProject($this->loadPicture($activity->getProject()));
            }
            if($activity->getOrganization() !== null){
                $activity->setOrganization($this->loadPicture($activity->getOrganization()));
            }
        }

   //     dd($this->dataResponse[0]->getProject());
        //success response
        return $this->successResponse("read_activity");
    }

    /**
     * @param Request $request
     * @return Response
     * @Route("", name="_delete", methods="delete")
     */
    public function remove(Request $request) : Response {
        //cleanXSS
        if($this->cleanXSS($request)
        ) return $this->response;

        // recover all data's request
        $this->dataRequest = $this->requestParameters->getData($this->request);
        if(!isset($this->dataRequest['id'])) return $this->BadRequestResponse(["missing parameter : id is required. "]);
        $this->dataRequest = array_merge($this->dataRequest, ["creator" => $this->getUser()->getId()]);

        if($this->getEntities(Activity::class, ['id', 'creator'])) return $this->response;

        if($this->deleteEntity($this->dataResponse[0])) return $this->response;

        return $this->successResponse();
    }

    public function getPics($activities){
        //download picture
        foreach($activities as $key => $activity){
            $activities[$key] = $this->loadPicture($activity);
            if($activity->getProject() !== null){
                $activity->setProject($this->loadPicture($activity->getProject()));
            }
            if($activity->getOrganization() !== null){
                $activity->setOrganization($this->loadPicture($activity->getOrganization()));
            }
        }
        return $activities;
    }
}
