<?php

namespace App\Controller;

use App\Entity\Organization;
use App\Entity\User;
use App\Exceptions\SecurityException;
use App\Exceptions\ViolationException;
use App\Service\LogService;
use App\Service\Request\ParametersValidator;
use App\Service\Request\RequestParameters;
use App\Service\Request\ResponseHandler;
use App\Service\Security\RequestSecurity;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class MembershipController
 * @package App\Controller
 * @Route("/member", name="member")
 */
class MembershipController extends AbstractController
{
    private RequestParameters $parameters;
    private ResponseHandler $responseHandler;
    protected EntityManagerInterface $entityManager;
    private ParametersValidator $validator;
    private LogService $logger;

    /**
     * OrgController constructor.
     * @param RequestParameters $requestParameters
     * @param ResponseHandler $responseHandler
     * @param EntityManagerInterface $entityManager
     * @param ParametersValidator $validator
     * @param LogService $logger
     */
    public function __construct( RequestParameters $requestParameters, ResponseHandler $responseHandler, EntityManagerInterface $entityManager, ParametersValidator $validator, LogService $logger)
    {
        $this->parameters = $requestParameters;
        $this->responseHandler = $responseHandler;
        $this->entityManager = $entityManager;
        $this->validator = $validator;
        $this->logger = $logger;
    }

    /**
     * @param Request $request
     * @return Response
     * @Route("/add", name="_add", methods="put")
     */
    public function addMember(Request $request) :Response {
        // recover all data's request
        $this->parameters->setData($request);

        //check required params
        try{ $this->parameters->hasData(["orgId","email"]); }
        catch(ViolationException $e) {
            $this->logger->logError($e, $this->getUser(), "error");
            return $this->responseHandler->BadRequestResponse($e->getViolationsList());
        }

        //if referent try to self add to membership
        if($this->parameters->getData("email") === $this->getUser()->getUsername() ){
            return $this->responseHandler->BadRequestResponse(["email"=> "referent can't be added into the membership"]);
        }

        try{
            //get Activity target for link
            $repository = $this->entityManager->getRepository(Organization::class);
            $orgData = $repository->findBy([
                "id" => $this->parameters->getData("orgId"),
                "referent" => $this->getUser()->getId()
            ]);

            if(count($orgData) === 0 ){
                $this->logger->logInfo(" Organization with id : ". $this->parameters->getData("orgId") ." not found " );
                return $this->responseHandler->BadRequestResponse(["Organization"=>"no_org_found"]);
            }
            $org = $orgData[0];

            //check params Validations
            try{ $this->validator->isInvalid(
                ["email"],
                [],
                User::class);
            } catch(ViolationException $e){
                $this->logger->logError($e, $this->getUser(), "error");
                return $this->responseHandler->BadRequestResponse($e->getViolationsList());
            }

            $repository = $this->entityManager->getRepository(User::class);
            $userData = $repository->findBy([
                "email" => $this->parameters->getData("email")]
            );
            if(count($userData) === 0 ){
                $this->logger->logInfo(" User with email : ". $this->parameters->getData("email") ." not found " );
                return $this->responseHandler->BadRequestResponse(["User"=>"no_user_found"]);
            }
            $user = $userData[0];

            //new member already in this org?
            foreach($org->getMembership() as $member){
                if($member->getId() === $user->getId()){
                    return $this->responseHandler->BadRequestResponse(["email"=> "user already added into the membership"]);
                }
            }

            $org->addMembership($user);
            $this->entityManager->flush();

            return $this->responseHandler->successResponse($org->getMembership()->toArray());

        }catch(Exception $e){
            $this->logger->logError($e,$this->getUser(),"error" );
            return $this->responseHandler->serverErrorResponse($e, "An error occured");
        }
    }

    /**
     * @param Request $request
     * @return Response|null
     * @Route("/remove", name="_remove", methods="put")
     */
    public function removeMember(Request $request){

        // recover all data's request
        $this->parameters->setData($request);

        //check required params
        try{ $this->parameters->hasData(["orgId","userId"]); }
        catch(ViolationException $e) {
            $this->logger->logError($e, $this->getUser(), "error");
            return $this->responseHandler->BadRequestResponse($e->getViolationsList());
        }

        try{

            $repository = $this->entityManager->getRepository(Organization::class);
            $orgData = $repository->findBy([
                    "id" => $this->parameters->getData("orgId"),
                    "referent" => $this->getUser()->getId()
                ]
            );
            if(count($orgData) === 0 ){
                $this->logger->logInfo(" Organization with id : ". $this->parameters->getData("orgId") ." not found " );
                return $this->responseHandler->BadRequestResponse(["Organization"=>"no_org_found"]);
            }
            $org = $orgData[0];

            $repository = $this->entityManager->getRepository(User::class);
            $userData = $repository->findBy([
                    "id" => $this->parameters->getData("userId")]
            );
            if(count($userData) === 0 ){
                $this->logger->logInfo(" User with id : ". $this->parameters->getData("userId") ." not found " );
                return $this->responseHandler->BadRequestResponse(["User"=>"no_user_found"]);
            }
            $user = $userData[0];

            $org->removeMembership($user);
            $this->entityManager->flush();

            //return updated membership
            $tab = $org->getMembership()->toArray();
            sort($tab);

            return $this->responseHandler->successResponse($tab);

        }catch(Exception $e){
            $this->logger->logError($e,$this->getUser(),"error" );
            return $this->responseHandler->serverErrorResponse($e, "An error occured");
        }
    }

    /**
     * @param Request $request
     * @return Response
     * @Route("/public", name="_get", methods="get")
     */
    public function getMembers(Request $request) : Response {
        // recover all data's request
        $this->parameters->setData($request);


        //check required params
        try{ $this->parameters->hasData(["orgId"]); }
        catch(ViolationException $e) {
            $this->logger->logError($e, $this->getUser(), "error");
            return $this->responseHandler->BadRequestResponse($e->getViolationsList());
        }

        try {
            $repository = $this->entityManager->getRepository(Organization::class);
            $orgData = $repository->findBy([
                    "id" => $this->parameters->getData("orgId")
                ]
            );
            if (count($orgData) === 0) {
                $this->logger->logInfo(" Organization with id : " . $this->parameters->getData("orgId") . " not found ");
                return $this->responseHandler->BadRequestResponse(["Organization" => "no_org_found"]);
            }
            $org = $orgData[0];

            return $this->responseHandler->successResponse($org->getMembership()->toArray());

        }catch(Exception $e){
            $this->logger->logError($e,$this->getUser(),"error" );
            return $this->responseHandler->serverErrorResponse($e, "An error occured");
        }
    }


}
