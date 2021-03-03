<?php

namespace App\Controller;

use App\Entity\FollowingProject;
use App\Entity\Project;
use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class FollowingProjectController
 * @package App\Controller
 * @Route("/followProject", name="follow_project")
 */
class FollowingProjectController extends CommonController
{
    /**
     * API endPoint: create or update a Following entity in a project
     * need $projectId, the project id target
     * need $email, the user target email, for an Assigned action, not for isFollowing( it's the current user)
     * need $isAssigning boolean for assign a user OR $isFollowing boolean for follow a user
     * @param Request $request
     * @return Response
     * @Route("", name="_add", methods="post")
     */
    public function add(Request $request) :Response {
        //cleanXSS
        if($this->cleanXSS($request)
        ) return $this->response;

        // recover all data's request
        $this->dataRequest = $this->requestParameters->getData($this->request);

        //check required params
        if(!$this->hasAllCriteria(["projectId"])){ return $this->response; }

        //todo faire un ou plutot...
        if(!isset($this->dataRequest['isAssigning']) && !$this->hasAllCriteria(['isFollowing'])) return $this->response;
        if(!isset($this->dataRequest['isFollowing']) && !$this->hasAllCriteria(['isAssigning'])) return $this->response;

        //need id variable for column id in database
        $this->dataRequest["id"] = $this->dataRequest['projectId'];
        $criterias = ["id"];

        if(isset($this->dataRequest['isAssigning'])){
            //need current user id as creator
            $this->dataRequest["creator"] = $this->getUser()->getId();
            $criterias[] = "creator";
        }

        if ($this->getEntities(Project::class, $criterias)) return $this->response; //error response
        if(empty($this->dataResponse)) return $this->BadRequestResponse(["project"=>"no_project_found"]);
        $project = $this->dataResponse[0];

        //check if it's an assign query by project creator
        if(isset($this->dataRequest["isAssigning"]))
        {
            if(!$this->addAssigned($project)) return $this->response; //error response
        }
        else {// add simple following tag
            if(!$this->addFollower($project)) return $this->response; //error response
        }

        $following = $this->dataResponse[0];
        if($following->getId() !== null){
            if($this->updateEntity($following)) return $this->response;
        }
        else {
            if($this->persistEntity($following)) return $this->response;
        }

        $project->addFollowing($following);

        if(isset($this->dataRequest["isAssigning"])){
            //response for assigning action
            $this->dataResponse = $project->getAssignedTeam();
            return $this->successResponse("team");
        }else {
            //response for following action
            //todo
            return $this->successResponse("collection");
        }
    }



    /**
     * API endPoint: update or remove a Following entity in a project
     * need $projectId, the project id target
     * need $userId, the user target id, for an Assigned action, not for isFollowing( it's the current user)
     * need $isAssigning boolean for unAssign a user OR $isFollowing boolean for unFollow a user
     * @param Request $request
     * @return Response|null
     * @Route("", name="_remove", methods="put")
     */
    public function remove(Request $request){
        //cleanXSS
        if($this->cleanXSS($request)
        ) return $this->response;

        // recover all data's request
        $this->dataRequest = $this->requestParameters->getData($this->request);

        //check required params
        if(!$this->hasAllCriteria(["projectId"])) return $this->response;
        if(!isset($this->dataRequest['isAssigning']) && !$this->hasAllCriteria(['isFollowing'])) return $this->response;
        if(!isset($this->dataRequest['isFollowing']) && !$this->hasAllCriteria(['isAssigning'])) return $this->response;

        //need id variable for column id in database
        $this->dataRequest["id"] = $this->dataRequest['projectId'];
        $criterias = ["id"];

        if(isset($this->dataRequest['isAssigning'])){
            //need current user id as creator
            $this->dataRequest["creator"] = $this->getUser()->getId();
            $criterias[] = "creator";
        }

        if ($this->getEntities(Project::class, $criterias)) return $this->response; //error response
        if(empty($this->dataResponse)) return $this->BadRequestResponse(["project"=>"no_project_found"]);
        $project = $this->dataResponse[0];

        //check if it's an assign query by project creator
        if(isset($this->dataRequest["isAssigning"]))
        {
            if(!$this->rmvAssigned($project)) return $this->response; // error response
        }
        else {// add simple following tag
            if(!$this->rmvFollower($project)) return $this->response; // error response
        }
        $following = $this->dataResponse[0];
        /**
         * ca dit que c'est potentiellement null ou response a cause des retour de reponse pour les erreurs...
         */
        if(!$following->isStillValid()){
            $this->deleteEntity($following);
            //    $project->removeFollowing($following);
        }

        //todo check conmportement sur remove et data retournées
    else{    if($this->updateEntity($following)) return $this->response;}

        $project->addFollowing($following);

        if(isset($this->dataRequest["isAssigning"])){
            //response for assigning action
            $this->dataResponse = $project->getAssignedTeam();
            return $this->successResponse("team");
        }else {
            //response for following action
            return $this->successResponse("collection");
        }

    }

    /**
     * API endPoint: return the following status for current user into a project
     * nedd $projectId the project id target
     * @param Request $request
     * @return Response
     * @Route("", name="_get", methods="get")
     */
    public function getFollowingStatus(Request $request) :Response{
        //cleanXSS
        if($this->cleanXSS($request)
        ) return $this->response;

        // recover all data's request
        $this->dataRequest = $this->requestParameters->getData($this->request);

        //check required params
        if(!$this->hasAllCriteria(["projectId"])) return $this->response;

        //need id variable for column id in database
        $this->dataRequest["id"] = $this->dataRequest['projectId'];

        if ($this->getEntities(Project::class, ["id"])) return $this->response; //if error response
        if(empty($this->dataResponse)) return $this->BadRequestResponse(["project"=>"no_project_found"]);
        $project = $this->dataResponse[0];

        $following = $project->getFollowingByUserId($this->getUser()->getId());

        $this->dataResponse = [$following->getIsFollowing()];
       /* if($following === null){
            $this->dataResponse = [ "isFollow"=>false ];
        }
        else { $this->dataResponse = [ "isFollow"=> true ];}*/

        return $this->successResponse();
    }

    /**
     * @param Project $project
     * @return bool
     */
    public function rmvAssigned(Project $project) :bool {
        if(!$this->hasAllCriteria(["userId"])) return false;

        $this->dataRequest["id"] = $this->dataRequest['userId'];
        //need user by id
        if ($this->getEntities(User::class, ["id"])) return false; // false if an error response prepared
        if(empty($this->dataResponse)) {
            $this->response = $this->BadRequestResponse(["email"=>"no_mail_account_found"]);
            return false;
        }
        $user = $this->dataResponse[0];

        //user have already a following?
        $following = $project->getFollowingByUserId($this->dataRequest['userId']);
        if($following === null) {
            $this->response = $this->BadRequestResponse(["user"=>"no_found_in_assigned"]);
            return false;
        }
        $following->setIsAssigning(false);

        $this->dataResponse = [$following];
        return true;
      //  return $following;
    }

    /**
     * @param Project $project
     * @return bool
     */
    public function rmvFollower(Project $project) :bool {

       // $this->dataRequest["id"] = $this->dataRequest['userId'];
        $this->dataRequest["id"] = $this->getUser()->getId();
        //need user by id
        if ($this->getEntities(User::class, ["id"])) return false; // false if an error response prepared
        if(empty($this->dataResponse)) {
            $this->response = $this->BadRequestResponse(["email"=>"no_mail_account_found"]);
            return false;
        }
        $user = $this->dataResponse[0];

        //user have already a following?
        $following = $project->getFollowingByUserId($user->getId());
        if($following === null) {
            $this->response = $this->BadRequestResponse(["user"=>"no_found_in_followers"]);
            return false;
        }
        $following->setIsFollowing(false);

        $this->dataResponse = [$following];
        return true;
    }

    /**
     * add a follower into project
     * @param Project $project
     * @return bool
     */
    public function addFollower(Project $project) :bool {

        //user have already a following?
        $following = $project->getFollowingByUserId($this->getUser()->getId());
        if($following === null){
            $following = new FollowingProject();
            $following->setIsAssigning(false);
            //$following->setFollower($this->dataRequest['userId']);
            $following->setFollower($this->getUser());
            $following->setProject($project);
        }

        $following->setIsFollowing(true);
        $this->dataResponse = [$following];
        return true;
    }

    /**
     * add an assigned user into project by his creator
     * @param Project $project
     * @return bool
     */
    public function addAssigned(Project $project) :bool {
        //check required params
        if(!$this->hasAllCriteria(["email"])) return false;

        //need user by email
        if ($this->getEntities(User::class, ["email"])) return false; //error response
        if(empty($this->dataResponse)) return false;
        $user = $this->dataResponse[0];

        //user have already a following?
        $following = $project->getFollowingByUserId($user->getId());
        if($following === null) {
            $following = new FollowingProject();
            $following->setIsFollowing(false);
            $following->setFollower($user);
            $following->setProject($project);
        }
        $following->setIsAssigning(true);

        $this->dataResponse = [$following];
        return true;
    }

}
