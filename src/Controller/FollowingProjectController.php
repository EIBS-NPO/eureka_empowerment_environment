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
 * @Route("/followProject"), name="follow_project")
 */
class FollowingProjectController extends CommonController
{
    //todo empecher les doublons ? ou pdejà fait vi clef?
    /**
     * assign a follow Teamed relation into the project.
     * @param Request $insecureRequest
     * @return Response
     * @Route("/assign", name="_add", methods="post")
     */
    public function addFollower(Request $insecureRequest) :Response {
        //cleanXSS
        if($this->cleanXSS($insecureRequest)
        ) return $this->response;

        // recover all data's request
        $this->dataRequest = $this->requestParameters->getData($this->request);

        if(!$this->dataRequest["projectId"]) {
            return $this->BadRequestResponse(["projectId" => "missing required parameter"]);
        }else if(!($this->dataRequest["email"])) {
            return $this->BadRequestResponse(["email"=>"missing required parameter"]);
        }else if($this->dataRequest['email'] === $this->getUser()->getUsername()){
            return $this->BadRequestResponse(["email"=> "referent can't be added into the membership"]);
        }

        $this->dataRequest["creator"] = $this->getUser()->getId();

        $this->dataRequest["id"] = $this->dataRequest['projectId'];

        //get query organization object by organization's id && referent's id
        if ($this->getEntities(Project::class, ["id", "creator"])) return $this->response;
        if(empty($this->dataResponse)) return $this->notFoundResponse();

        $project = $this->dataResponse[0];
        unset($this->dataRequest["projectId"]);

        //get query organization object by organization's id && referent's id
        if ($this->getEntities(User::class, ["email"])) return $this->response;
        if(empty($this->dataResponse)) return $this->BadRequestResponse(["email"=>"aucun compte n'a été trouvé pour cet email"]);

        //new member already in this org?
        //todo already in follower? check if assign and don't make a new FollowingProject
        foreach($project->getFollowers() as $follower){
            if($follower->getId() === $this->dataResponse[0]->getId()){
                return $this->BadRequestResponse(["email"=> "user already added into the team"]);
            }
        }
//todo else make a new
        $following = new FollowingProject();
        $following->setFollower($this->dataResponse[0]);
        $following->setProject($project);
        $following->setIsAssigned(true);

        $project->addFollower($following);

        if($this->updateEntity($project)) return $this->response;

        $this->dataResponse = $project->getTeam();

        return $this->successResponse();
    }

    /**
     * assigned tag for the follower, but he always a follower, just not teamed
     * @param Request $insecureRequest
     * @return Response|null
     * @Route("/unassign", name="_remove", methods="put")
     */
    public function removeAssigned(Request $insecureRequest){
        //cleanXSS
        if($this->cleanXSS($insecureRequest)
        ) return $this->response;

        // recover all data's request
        $this->dataRequest = $this->requestParameters->getData($this->request);
        if(!isset($this->dataRequest["projectId"])) {
            return $this->BadRequestResponse(["projectId" => "missing required parameter"]);
        }
        if(!isset($this->dataRequest["userId"])){
            return $this->BadRequestResponse(["userId" => "missing required parameter"]);
        }



        $this->dataRequest["creator"] = $this->getUser()->getId();

        $this->dataRequest["id"] = $this->dataRequest['projectId'];



        //get query organization object by organization's id && referent's id
        if ($this->getEntities(Project::class, ["id", "creator"])) return $this->response;
        if(empty($this->dataResponse)) return $this->notFoundResponse();

        $project = $this->dataResponse[0];

        if ($this->getEntities(FollowingProject::class, ["userId", "projectId"])) return $this->response;
        if(empty($this->dataResponse)) return $this->notFoundResponse();




        $following = $this->dataResponse[0];
        $following->setIsAssigned(false);

        if($this->updateEntity($following)) return $this->response;


        $this->dataResponse = $project->getTeam();

        return $this->successResponse();
    }

    /**
     * unfollow a project
     * @param Request $insecureRequest
     * @Route("unfollow", name="_remove", methods="put")
     * @return Response|null
     */
    public function removeFollower(Request $insecureRequest){
        //cleanXSS
        if($this->cleanXSS($insecureRequest)
        ) return $this->response;

        // recover all data's request
        $this->dataRequest = $this->requestParameters->getData($this->request);

        // recover all data's request
        $this->dataRequest = $this->requestParameters->getData($this->request);
        if(!isset($this->dataRequest["projectId"])) {
            return $this->BadRequestResponse(["projectId" => "missing required parameter"]);
        }

        $this->dataRequest['userId'] = $this->getUser()->getId();

        if ($this->getEntities(FollowingProject::class, ["userId", "projectId"])) return $this->response;
        if(empty($this->dataResponse)) return $this->notFoundResponse();
        $following = $this->dataResponse;

        //get query organization object by organization's id && referent's id
        if ($this->getEntities(User::class, ["email"])) return $this->response;
        if(empty($this->dataResponse)) return $this->notFoundResponse();

        $user = $this->dataResponse[0];
        $user->removeFollowingProject($following);
        $this->updateEntity($user);

        return $this->successResponse();
    }

    /**
     * @param Request $insecureRequest
     * @return Response
     * @Route("/public", name="_get", methods="get")
     */
    public function getMembers(Request $insecureRequest) : Response {
        //cleanXSS
        if($this->cleanXSS($insecureRequest)
        ) return $this->response;

        // recover all data's request
        $this->dataRequest = $this->requestParameters->getData($this->request);

        if(!isset($this->dataRequest["orgId"])){
            return $this->notFoundResponse();
        }

        $this->dataRequest["id"] = $this->dataRequest['orgId'];

        //get query organization object by organization's id && referent's id
        if ($this->getEntities(Organization::class, ["id"])) return $this->response;
        if(!empty($this->dataResponse)){
            $this->dataResponse = $this->dataResponse[0]->getMembership()->toArray();
            unset($this->dataRequest["orgId"]);
        }else {
            $this->notFoundResponse();
        }

        return $this->successResponse();

    }
}
