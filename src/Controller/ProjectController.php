<?php

namespace App\Controller;

use App\Entity\Organization;
use App\Entity\Project;
use App\Entity\User;
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
     * @param Request $request
     * @return Response
     * @throws Exception
     */
    public function create(Request $request): Response
    {
        //cleanXSS
        if($this->cleanXSS($request)
        ) return $this->response;

        // recover all data's request
        $this->dataRequest = $this->requestParameters->getData($this->request);

        // add default required values && convert datetime
        $this->dataRequest = array_merge($this->dataRequest, ["creator" => $this->getUser()]);

        if(isset($this->dataRequest['startDate'])){
            $this->dataRequest['startDate'] = new DateTime ($this->dataRequest["startDate"]);
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
            ["creator", "title", "description", "startDate"],
            ["endDate", "organization"],
            Project::class)
        ) return $this->response;


        //create project object && set validated fields
        $project = $this->setEntity(new Project(), ["creator", "title", "description", "startDate", "endDate", "organization"]);

        //persist the new project
        if($this->persistEntity($project)) return $this->response;

        //success
        return $this->successResponse();
    }





    /**
     * @Route("", name="_put", methods="put")
     * @param Request $request
     * @return Response
     * @throws Exception
     */
    public function updateProject (Request $request) :Response
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

        //validate id and recover projectObject with currentUser id (creator)
        if($this->getEntities(Project::class, ['id', 'creator'])) return $this->response;
        $project = $this->dataResponse[0];

        //Link or unlink with an org
        if(!isset($this->dataRequest['orgId'])){
            $this->dataRequest['organization'] = null;
        }else {
            if($this->getLinkedEntity(Organization::class, "organization", 'orgId')  ) return $this->response;
        }

        //validity control
        if($this->isInvalid(
            null,
            ["title", "description", "startDate", "endDate", "organization"],
            Project::class)
        ) return $this->response;

        //set project's validated fields
        $project = $this->setEntity($project, ["title", "description", "startDate", "endDate", "organization"]);

        //persist updated project
        if($this->updateEntity($project)) return $this->response;

        //final response
        return $this->successResponse();
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
        if($this->getEntities(Project::class, ['id', 'creator'])) return $this->response;
        $project = $this->dataResponse[0];

        //todo gestion des files dans la requête
        // todo content-type getResponse()->setContentType('image/jpeg')

        if($this->uploadPicture($project, $this->dataRequest['image'])) return $this->response;

        if($this->isInvalid(
            null,
            ["picturePath"],
            Project::class)
        ) return $this->response;

        //set project's validated fields
        $project = $this->setEntity($project, ["picturePath"]);

        //persist updated project
        if($this->updateEntity($project)) return $this->response;

        //download picture
        $this->dataResponse = [$this->loadPicture($project)];

        //final response
        return $this->successResponse();
    }




    /**
     * returns all projects
     * @Route("/public", name="_get_public", methods="get")
     * @param Request $request
     * @return Response
     */
    public function getPublicProjects(Request $request): Response {
        //cleanXSS
        if($this->cleanXSS($request)
        ) return $this->response;

        // recover all data's request
        $this->dataRequest = $this->requestParameters->getData($this->request);

        //todo switch sur dataRequest['context'] => 'public' || 'creator' || 'assigned'
        //todo for dispatch the good request by access rules.

        //get one public project
        if(isset($this->dataRequest["id"])){
            if($this->getEntities(Project::class, ["id"] )) return $this->response;
        } else {
            //get all public project
            if($this->getEntities(Project::class, [] )) return $this->response;
        }

        $projects =$this->dataResponse;

        //filter for return only publics resources
        foreach($projects as $project){
            $project->setActivities($project->getOnlyPublicActivities());
        }

        //download picture
        foreach($projects as $key => $project){
            foreach($project->getActivities() as $activity){
                $activity = $this->loadPicture(($activity));
            }
            if($project->getOrganization() !== null ){
                $project->setOrganization( $this->loadPicture($project->getOrganization()));
            }
            $project = $this->loadPicture($project);
        }
        $this->dataResponse = $projects;

        //success response
        return $this->successResponse("read_project");
    }

    /**
     * returns to a user his created projects
     * @Route("", name="_get", methods="get")
     * @param Request $request
     * @return Response
     */
    public function getProjects(Request $request): Response {
        //cleanXSS
        if($this->cleanXSS($request)) return $this->response;

        // recover all data's request
        $this->dataRequest = $this->requestParameters->getData($this->request);
//dd($this->dataRequest);
        $this->dataRequest["id"] = $this->getUser()->getId();
        if ($this->getEntities(User::class, ["id"])) return $this->response;
        $user = $this->dataResponse[0];

        $criterias = [];
      /*  switch($this->dataRequest['ctx']){
            case 'assigned':
                $this->dataRequest["assigned"] = $this->getUser()->getId();
                $criterias[]='assigned';
                break;
            case 'creator':
                $this->dataRequest["creator"] = $this->getUser()->getId();
                $criterias[]='creator';
                break;
        }*/

        //if query for only one
        if(isset($this->dataRequest["projectId"])) {
            $this->dataRequest["id"] = $this->dataRequest["projectId"];
            if($this->getEntities(Project::class, ["id"] )) return $this->response;
               $projects = $this->dataResponse;
        }else {
            $projects = $user->getProjects()->toArray();
        }
    //    if($this->getEntities(Project::class, $criterias )) return $this->response;
     //   $projects = $this->dataResponse;
    //    dd($projects);

        foreach($projects as $project){
            if(!$project->isAssign($user)){
                $project->setActivities($project->getOnlyPublicActivities());
            }
        }

        //download picture
        foreach($projects as $key => $project){
            foreach($project->getActivities() as $activity){
                $activity = $this->loadPicture(($activity));
            }
            if($project->getOrganization() !== null ){
                $project->setOrganization( $this->loadPicture($project->getOrganization()));
            }
            $projects[$key] = $this->loadPicture($project);
        }
        $this->dataResponse = $projects;
   //     dd($projects);

    //    dd($this->dataResponse);
        //success response
        return $this->successResponse("read_project");
    }

    /**
     * @param Request $insecureRequest
     * @return Response|null
     * @Route("/followed", name="_followed", methods="get")
     */
    public function getMyFollowing (Request $insecureRequest) {
        //cleanXSS
        if($this->cleanXSS($insecureRequest)
        ) return $this->response;

        $this->dataRequest["id"] = $this->getUser()->getId();

        if ($this->getEntities(User::class, ["id"])) return $this->response;

        $this->dataResponse = $this->dataResponse[0]->getFollowedProjects();

        foreach($this->dataResponse as $key => $follower){
            $this->dataResponse[$key] = $this->loadPicture($follower);
        }

        return $this->successResponse("read_project");
    }

    /**
     * @param Request $request
     * @return Response
     * @Route("/assigned", name="_assigned", methods="get")
     */
    public function getAssignedProject(Request $request){
        //cleanXSS
        if($this->cleanXSS($request)) return $this->response;

        $this->dataRequest['id'] = $this->getUser()->getId();
        if($this->getEntities(User::class, ['id'] )) return $this->response;
        $user = $this->dataResponse[0];

        $this->dataResponse = $user->getAssignedProjects();
        foreach($this->dataResponse as $key => $follower){
            $this->dataResponse[$key] = $this->loadPicture($follower);
        }

        return $this->successResponse();
    }

    /**
     * API andPoint: return the assigned user list for a project
     * need $projectId, the project id target
     * @param Request $request
     * @return Response|null
     * @Route("/team/public", name="_team", methods="get")
     */
    public function getTeam(Request $request) :Response {
        //cleanXSS
        if($this->cleanXSS( $request )) return $this->response;

        // recover all data's request
        $this->dataRequest = $this->requestParameters->getData($this->request);

        //check required params
        if(!$this->hasAllCriteria(["projectId"])) return $this->response;

        //need id column
        $this->dataRequest['id'] = $this->dataRequest["projectId"];
        if($this->getEntities(Project::class, ["id"] )) return $this->response;
        if(empty($this->dataResponse)) return $this->BadRequestResponse(["project"=>"no_project_found"]);
        $project = $this->dataResponse[0];

        //get collection of assigned user
        $this->dataResponse = $project->getAssignedTeam();

        return $this->successResponse();
    }

    /**
     * @param Request $request
     * @return Response
     * @Route("/manageActivity", name="_add_activity", methods="put")
     */
    public function manageActivity(Request $request) {
        //cleanXSS
        if($this->cleanXSS( $request )) return $this->response;

        // recover all data's request
        $this->dataRequest = $this->requestParameters->getData($this->request);

        //check required params
        if(!$this->hasAllCriteria(["activityId", "projectId"])) return $this->response;

        $this->dataRequest['id'] = $this->getUser()->getId();
        if($this->getEntities(User::class, ["id"] )) return $this->response;
        $user = $this->dataResponse[0];

        $project = $user->getAssignedProjectById($this->dataRequest["projectId"]);
        if($project === null) return $this->BadRequestResponse(["project" => "user not assigned into this project"]);

        $activity = $user->getActivity($this->dataRequest["activityId"]);
        if($project === null) return $this->BadRequestResponse(["activity" => "user isn't the owner of the activity"]);

        if($project->getActivityById($this->dataRequest["activityId"]) === null){
            $project->addActivity($activity);
        }else{
            $project->removeActivity($activity);
        }

        if($this->updateEntity($project)) return $this->response;

        $this->dataResponse = ["success"];
        return $this->successResponse();
    }

    /**
     * @param Request $request
     * @return Response
     * @Route("/manageOrg", name="_manage_org", methods="put")
     */
    public function manageOrg(Request $request){
        //cleanXSS
        if($this->cleanXSS( $request )) return $this->response;

        // recover all data's request
        $this->dataRequest = $this->requestParameters->getData($this->request);

        //check required params
        if(!$this->hasAllCriteria(["orgId", "projectId"])) return $this->response;

        $this->dataRequest['id'] = $this->getUser()->getId();
        if($this->getEntities(User::class, ["id"] )) return $this->response;
        $user = $this->dataResponse[0];

        $project = $user->getAssignedProjectById($this->dataRequest["projectId"]);
        if($project === null) return $this->BadRequestResponse(["project" => "user not assigned into this project"]);

        $this->dataRequest['id'] = $this->dataRequest['orgId'];
        if($this->getEntities(Organization::class, ["id"] )) return $this->response;
        $org = $this->dataResponse[0];

        if(!$org->isMember($user)){return $this->unauthorizedResponse("your not member of this organization");}

        if($project->getOrganization() === null){
            $project->setOrganization($org);
        }else {
            $project->setOrganization(null);
        }

        $this->updateEntity($project);

        $this->dataResponse = ["success"];
        return $this->successResponse();
    }
}
