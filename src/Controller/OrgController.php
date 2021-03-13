<?php

namespace App\Controller;

use App\Entity\Activity;
use App\Entity\Organization;
use App\Entity\Project;
use App\Entity\User;
use App\Service\FileHandler;
use App\Service\LogService;
use App\Service\Request\ParametersValidator;
use App\Service\Request\RequestParameters;
use App\Service\Request\ResponseHandler;
use App\Service\Security\RequestSecurity;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class OrgController
 * @package App\Controller
 * @Route("/org", name="org")
 */
class OrgController extends CommonController
{
    private RequestSecurity $security;
    private RequestParameters $parameters;
    private ResponseHandler $responseHandler;
    private ParametersValidator $validator;
    protected EntityManagerInterface $entityManager;
    protected FileHandler $fileHandler;
    private LogService $logger;

    /**
     * OrgController constructor.
     * @param RequestSecurity $requestSecurity
     * @param RequestParameters $requestParameters
     * @param ResponseHandler $responseHandler
     * @param ParametersValidator $validator
     * @param EntityManagerInterface $entityManager
     * @param FileHandler $fileHandler
     * @param LogService $logger
     */
    public function __construct(RequestSecurity $requestSecurity, RequestParameters $requestParameters, ResponseHandler $responseHandler, ParametersValidator $validator, EntityManagerInterface $entityManager, FileHandler $fileHandler, LogService $logger)
    {
        $this->security = $requestSecurity;
        $this->parameters = $requestParameters;
        $this->responseHandler = $responseHandler;
        $this->validator = $validator;
        $this->entityManager = $entityManager;
        $this->fileHandler = $fileHandler;
        $this->logger = $logger;
    }


    /**
     * @Route("", name="_registration", methods="post")
     * @param Request $insecureRequest
     * @return Response
     */
    public function create(Request $insecureRequest) :Response
    {
        //cleanXSS
        if($this->cleanXSS($insecureRequest)
        ) return $this->response;

        // recover all data's request
        $this->dataRequest = $this->requestParameters->getData($this->request);
        $this->dataRequest = array_merge($this->dataRequest, ["referent" => $this->getUser()]);

        //todo add address optional

        //dataRequest Validations
        if($this->isInvalid(
            ["name", "type", "email", "referent"],
            ["phone", 'description'],
            Organization::class)
        ) return $this->response;

        //create user object && set validated fields
        $org = $this->setEntity(new Organization(),["name", "type", "email", "description", "referent", "phone"]);

        //persist the new organization
        if($this->persistEntity($org)) return $this->response;

        //success response
        return $this->successResponse();
    }






    /**
     * @Route("/public", name="_get_public", methods="get")
     * @param Request $insecureRequest
     * @return Response
     */
    public function getPublicOrg(Request $insecureRequest) :Response
    {
        //cleanXSS
        if($this->cleanXSS($insecureRequest)
        ) return $this->response;

        // recover all data's request
        $this->dataRequest = $this->requestParameters->getData($this->request);

        $criterias = [];
        if(isset($this->dataRequest['id'])){
            $criterias[]="id";
        }

        //get query, if id not define, query getALL
        if($this->getEntities(Organization::class, $criterias )) return $this->response;
        $orgs = $this->dataResponse;

        //only with public resources
        foreach($orgs as $org) {
            $org->setActivities($org->getOnlyPublicActivities());
        }

        //download picture
        foreach($orgs as $key => $org){
            $org= $this->loadPicture($org);
            foreach($org->getActivities() as $activity){
                $activity = $this->loadPicture(($activity));
            }
            foreach($org->getProjects() as $project){
                $project = $this->loadPicture(($project));
            }
            foreach($org->getMembership() as $member){
                $member = $this->loadPicture($member);
            }
        }
        $this->dataResponse = $orgs;

        //success response
        return $this->successResponse("read_org");
    }

    /**
     * @Route("", name="_get", methods="get")
     * @param Request $insecureRequest
     * @return Response|null
     */
    public function getOrg(Request $insecureRequest){
        //cleanXSS
        if($this->cleanXSS($insecureRequest)
        ) return $this->response;

        // recover all data's request
        $this->dataRequest = $this->requestParameters->getData($this->request);
        /*$this->dataRequest["referent"] = $this->getUser()->getId();*/

      /*  $this->dataRequest['id'] = $this->getUser()->getId();
        if($this->getEntities(User::class, ["id"] )) return $this->response;
        $user = $this->dataResponse[0];*/

      //ca ne doit pas partir des param de requete, mais de verification!

        /**
         * comme les droit de modif et d'accès ne sont utilisé que sur des page profile des org, on ne recupere les info prové que sur des requetes d'org unique, selon les tests.
         * pour les listes d'org, aucune infos senssible pour personne.
         */
    /*    if(isset($this->$this->dataRequest["orgId"])){ //request for one org
            //vérifier si user a cette orgId dans ces org comme referent sinon comme membre
            $orgRes = $user->getOrgById($this->dataRequest["orgId"]);
            if(!$orgRes){
                $orgRes = $user->getMemberOfById($this->dataRequest["orgId"]);
            }
            if(!$orgRes){
                $this->dataRequest['id'] = $this->dataRequest['orgId'];
                if($this->getEntities(Organization::class, ["id"] )) return $this->response;
                $orgRes = $this->dataResponse[0]->getOnlyPublic();

            }
        }else { //request for many org
            if($this->dataRequest["my"]){

            }
        }*/



        //validation requestParam and recover organization(s)
        if(isset($this->dataRequest['id'])){
            if($this->getEntities(Organization::class, ["id"] )) return $this->response;
        }else {
            if($this->getEntities(Organization::class, [] )) return $this->response;
        }
        $orgs = $this->dataResponse;

        $this->dataRequest['id'] = $this->getUser()->getId();
        if($this->getEntities(User::class, ["id"] )) return $this->response;
        $user = $this->dataResponse[0];
        //isreferent or assign?
        foreach($orgs as $org){
            if(!$org->isMember($user)){
                $org->setActivities($org->getOnlyPublicActivities());
            }
        }

        //download picture
        foreach($orgs as $key => $org){
            $org= $this->loadPicture($org);
            foreach($org->getActivities() as $activity){
                $activity = $this->loadPicture(($activity));
            }
            foreach($org->getProjects() as $project){
                $project = $this->loadPicture(($project));
            }
            foreach($org->getMembership() as $member){
                $member = $this->loadPicture($member);
            }
        }
        $this->dataResponse = $orgs;

//dd($this->dataResponse);
        //success response
        return $this->successResponse("read_org");
    }

    //todo ajout logo update

    /**
     * @param Request $insecureRequest
     * @return Response
     * @ROUTE("", name="_put", methods="put")
     */
    public function updateOrganization(Request $insecureRequest) :Response
    {
        //cleanXSS 603771a4a4792_blob.jpeg
        if($this->cleanXSS($insecureRequest)
        ) return $this->response;

        // recover all data's request
        $this->dataRequest = $this->requestParameters->getData($this->request);
        $this->dataRequest = array_merge($this->dataRequest, ["referent" => $this->getUser()->getId()]);

        //validation for paramRequest && get query organization object by organization's id && referent's id
        if($this->getEntities(Organization::class, ["id", "referent"] )) return $this->response;

        if(!empty($this->dataResponse)){
            if($this->isInvalid(
                null,
                ["type", "name", "description", "email", "phone"],
                Organization::class)
            ) return $this->response;

            //set organization's validated fields
            $org = $this->setEntity($this->dataResponse[0], ["type", "name", "description", "email", "phone"]);

            //persist updated org
            if($this->updateEntity($org)) return $this->response;
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
        $this->dataRequest = array_merge($this->dataRequest, ["referent" => $this->getUser()->getId()]);

        //todo controle with assert for image and configLimite (dans pictureHandler,( ou fileHandler)
        /*if(!isset($this->dataRequest["picture"])) {

        }*/

        //get query
        if($this->getEntities(Organization::class, ['id', 'referent'])) return $this->response;
        $org = $this->dataResponse[0];

        //todo gestion des files dans la requête
        // todo content-type getResponse()->setContentType('image/jpeg')

        if($this->uploadPicture($org, $this->dataRequest['image'])) return $this->response;

        //todo inutile...
        if($this->isInvalid(
            null,
            ["picturePath"],
            Organization::class)
        ) return $this->response;

        //set project's validated fields
        $org = $this->setEntity($org, ["picturePath"]);

        //persist updated project
        if($this->updateEntity($org)) return $this->response;

        //download picture
        $this->dataResponse = [$this->loadPicture($org)];

        //final response
        return $this->successResponse();
    }

    /**
     * @param Request $request
     * @return Response
     * @Route("/manageActivity", name="_manage_Activity", methods="put")
     */
    public function manageActivity(Request $request){
        //cleanXSS
        if($this->cleanXSS( $request )) return $this->response;

        // recover all data's request
        $this->dataRequest = $this->requestParameters->getData($this->request);

        //check required params
        if(!$this->hasAllCriteria(["activityId", "orgId"])) return $this->response;

        $this->dataRequest['id'] = $this->getUser()->getId();
        if($this->getEntities(User::class, ["id"] )) return $this->response;
        $user = $this->dataResponse[0];

        $this->dataRequest['id'] = $this->dataRequest['orgId'];
        if($this->getEntities(Organization::class, ["id"] )) return $this->response;
        $org = $this->dataResponse[0];

        if(!$org->isMember($user)){return $this->unauthorizedResponse("your not member of this organization");}

        $this->dataRequest['id'] = $this->dataRequest['activityId'];
        if($this->getEntities(Activity::class, ["id"] )) return $this->response;
        $activity = $this->dataResponse[0];

        //if activity have the organization, remove it
        if($activity->getOrganization() !== null && $activity->getOrganization()->getId() === $org->getId()){
            $activity->setOrganization(null);
        }
        else { //add
            $activity->setOrganization($org);
        }

        if($this->updateEntity($activity)) return $this->response;

        $this->dataResponse = ["success"];
        return $this->successResponse();
    }

    /**
     * @param Request $request
     * @return Response
     * @Route("/manageProject", name="_manage_Project", methods="put")
     */
    public function manageProject(Request $request){
        //cleanXSS
        if($this->cleanXSS( $request )) return $this->response;

        // recover all data's request
        $this->dataRequest = $this->requestParameters->getData($this->request);

        //check required params
        if(!$this->hasAllCriteria(["projectId", "orgId"])) return $this->response;

        $this->dataRequest['id'] = $this->getUser()->getId();
        if($this->getEntities(User::class, ["id"] )) return $this->response;
        $user = $this->dataResponse[0];

        $this->dataRequest['id'] = $this->dataRequest['orgId'];
        if($this->getEntities(Organization::class, ["id"] )) return $this->response;
        $org = $this->dataResponse[0];

        if(!$org->isMember($user)){return $this->unauthorizedResponse("your not member of this organization");}

        $this->dataRequest['id'] = $this->dataRequest['projectId'];
        if($this->getEntities(Project::class, ["id"] )) return $this->response;
        $project = $this->dataResponse[0];

        //if activity have the organization, remove it
        if($project->getOrganization() !== null && $project->getOrganization()->getId() === $org->getId()){
            $project->setOrganization(null);
        }
        else { //add
            $project->setOrganization($org);
        }

        if($this->updateEntity($project)) return $this->response;

        $this->dataResponse = ["success"];
        return $this->successResponse();
    }

    /**
     * @param Request $request
     * @return Response
     * @Route("/membered", name="_membered", methods="get")
     */
    public function getMemberedOrg (Request $request){
        $this->dataRequest['id'] = $this->getUser()->getId();
        if($this->getEntities(User::class, ["id"] )) return $this->response;
        $user = $this->dataResponse[0];

        $this->dataResponse = $user->getMemberOf()->toArray();

        return $this->successResponse();
    }

    /*public function hasAccess(Request $request){
        //cleanXSS
        if($this->cleanXSS( $request )) return $this->response;

        // recover all data's request
        $this->dataRequest = $this->requestParameters->getData($this->request);

        //check required params
        if(!$this->hasAllCriteria(["orgId"])) return $this->response;

        $this->dataRequest['id'] = $this->dataRequest['orgId'];
        if($this->getEntities(Organization::class, ["id"] )) return $this->response;
        $org = $this->dataResponse[0];
    }*/
}
