<?php

namespace App\Controller;

use App\Entity\Activity;
use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class FollowingActivityController
 * @package App\Controller
 * @Route("/followActivity", name="follow_activity")
 */
class FollowingActivityController extends CommonController
{
    /**
     * @param Request $insecureRequest
     * @return Response
     * @Route("/add", name="_add", methods="put")
     */
    public function addFollower(Request $insecureRequest) :Response {
        //cleanXSS
        if($this->cleanXSS($insecureRequest)
        ) return $this->response;

        // recover all data's request
        $this->dataRequest = $this->requestParameters->getData($this->request);
        if((!$this->dataRequest["activityId"])) return $this->BadRequestResponse(["missing parameter : activityId is required. "]);

        //get Activity target for link
        $this->getLinkedEntity(Activity::class,"followingActivities", "activityId");

        //getUser
        $this->dataRequest["id"] = $this->getUser()->getId();
        if ($this->getEntities(User::class, ["id"])) return $this->response;

        $user = $this->dataResponse[0];
        $user->addFollowingActivity($this->dataRequest["followingActivities"]);

        //todo check if double possible

        if($this->updateEntity($user)) return $this->response;

        //todo quel retour?
        return $this->successResponse();
    }

    /**
     * @param Request $insecureRequest
     * @return Response|null
     * @Route("/remove", name="_remove", methods="put")
     */
    public function removeFollower(Request $insecureRequest){
        //cleanXSS
        if($this->cleanXSS($insecureRequest)
        ) return $this->response;

        // recover all data's request
        $this->dataRequest = $this->requestParameters->getData($this->request);
        if(!isset($this->dataRequest["activityId"]))return $this->BadRequestResponse(["missing parameter : activityId is required. "]);

        $this->dataRequest["userId"] = $this->getUser()->getId();
        $this->dataRequest["id"] = $this->getUser()->getId();

        if ($this->getEntities(User::class, ["id"])) return $this->response;
        $user = $this->dataResponse[0];

        //get Activity target for link
        $this->getLinkedEntity(Activity::class,"followingActivities", "activityId");

        $user->removeFollowingActivity($this->dataRequest["followingActivities"]);

        if($this->updateEntity($user)) return $this->response;

        //todo retour?

        return $this->successResponse();
    }

    /**
     * @param Request $insecureRequest
     * @return Response
     * @Route("/public", name="_get", methods="get")
     */
    public function getFellowers(Request $insecureRequest) : Response {
        //cleanXSS
        if($this->cleanXSS($insecureRequest)
        ) return $this->response;

        // recover all data's request
        $this->dataRequest = $this->requestParameters->getData($this->request);
        if(!isset($this->dataRequest["id"]))return $this->BadRequestResponse(["missing parameter : id is required. "]);

        //get query organization object by organization's id && referent's id
        if ($this->getEntities(Activity::class, ["id"])) return $this->response;
        if(!empty($this->dataResponse)){
            $this->dataResponse = $this->dataResponse[0]->getFollowers()->toArray();
            foreach($this->dataResponse as $key => $follower){
                $this->dataResponse[$key] = $this->loadPicture($follower);
            }
        }else {
            $this->notFoundResponse();
        }

        return $this->successResponse();

    }

    /**
     * @param Request $insecureRequest
     * @return Response|null
     * @Route("/myFavorites", name="_myFavorites", methods="get")
     */
    public function getMyFollowing (Request $insecureRequest) {
        //cleanXSS
        if($this->cleanXSS($insecureRequest)
        ) return $this->response;

        // recover all data's request
        $this->dataRequest = $this->requestParameters->getData($this->request);
        $this->dataRequest["id"] = $this->getUser()->getId();

        //get query organization object by organization's id && referent's id
        if ($this->getEntities(User::class, ["id"])) return $this->response;
        if(!empty($this->dataResponse)){
            $this->dataResponse = $this->dataResponse[0]->getFollowingActivities()->toArray();
            foreach($this->dataResponse as $key => $follower){
                $this->dataResponse[$key] = $this->loadPicture($follower);
            }
        }else {
            $this->notFoundResponse();
        }
        return $this->successResponse();
    }

    /**
     * API endPoint: return the following status for current user into a activity
     * nedd $projectId the activity id target
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
        if(!$this->hasAllCriteria(["activityId"])) return $this->response;

        //need id variable for column id in database
        $this->dataRequest["id"] = $this->dataRequest['activityId'];

        if ($this->getEntities(Activity::class, ["id"])) return $this->response; //if error response
        if(empty($this->dataResponse)) return $this->BadRequestResponse(["activity"=>"no_activity_found"]);
        $activity = $this->dataResponse[0];

      //  $following = $activity->isFollowUserId($this->getUser()->getId());

        $this->dataResponse = [$activity->isFollowByUserId($this->getUser()->getId())];
        /* if($following === null){
             $this->dataResponse = [ "isFollow"=>false ];
         }
         else { $this->dataResponse = [ "isFollow"=> true ];}*/

        return $this->successResponse();
    }
}
