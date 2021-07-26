<?php

namespace App\Controller\admin;

use App\Entity\Organization;
use App\Entity\User;
use App\Exceptions\SecurityException;
use App\Exceptions\ViolationException;
use App\Service\FileHandler;
use App\Service\LogService;
use App\Service\Request\ParametersValidator;
use App\Service\Request\RequestParameters;
use App\Service\Request\ResponseHandler;
use App\Service\Security\RequestSecurity;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class UserAdminController
 * @package App\Controller
 * @Route("/admin/user", name="admin")
 */
class UserAdminController
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
     * @Route("", name="_get_user", methods="get")
     * @param Request $request
     * @return Response
     */
    public function getUserInfo(Request $request): Response
    {
        /*try{$this->security->cleanXSS($request);}
        catch(SecurityException $e) {
            $this->logger->logError($e, $this->getUser(), "warning");
            return $this->responseHandler->forbidden();
        }*/

        // recover all data's request
        $this->parameters->setData($request);

        $criteria = null;
        if($this->parameters->getData("email") != false){
            try {
                $this->validator->isInvalid(
                    null,
                    ["email"],
                    User::class);
            } catch(ViolationException $e){
                $this->logger->logError($e, $this->getUser(), "error");
                return $this->responseHandler->BadRequestResponse($e->getViolationsList());
            }
        }
            if($this->parameters->getData("id") != false){
                try {
                    $this->validator->isInvalid(
                        null,
                        ["id"],
                        User::class);
                } catch(ViolationException $e){
                    $this->logger->logError($e, $this->getUser(), "error");
                    return $this->responseHandler->BadRequestResponse($e->getViolationsList());
                }
            }

        $repository = $this->entityManager->getRepository(User::class);
        //get query, if id not define, query getALL
        try{
            if(isset($criterias['id'])){
                $dataResponse = $repository->findBy($criterias);
            }else {
                $dataResponse = $repository->findAll();
            }
        }catch(Exception $e){
            $this->logger->logError($e,$this->getUser(),"error" );
            return $this->responseHandler->serverErrorResponse($e, "An error occured");
        }

        //success response
        return $this->responseHandler->successResponse($dataResponse);
    }

    /**
     * @Route("", name="_udpate_user", methods="put")
     * @param Request $request
     * @return Response
     */
    public function updateUserInfo(Request $request) : Response
    {
        /*try{$this->security->cleanXSS($request);}
        catch(SecurityException $e) {
            $this->logger->logError($e, $this->getUser(), "warning");
            return $this->responseHandler->forbidden();
        }*/

        // recover all data's request
        $this->parameters->setData($request);

        //check if required params exist
        try{ $this->parameters->hasData(["id"]); }
        catch(ViolationException $e) {
            $this->logger->logError($e, $this->getUser(), "error");
            return $this->responseHandler->BadRequestResponse($e->getViolationsList());
        }

        //check params Validations
        try{$this->validator->isInvalid(
                [],
                ["id", "firstname", "lastname", "phone", "mobile"],
                User::class);
        } catch(ViolationException $e){
            $this->logger->logError($e, $this->getUser(), "error");
            return $this->responseHandler->BadRequestResponse($e->getViolationsList());
        }

        $repository = $this->entityManager->getRepository(User::class);
        //get query, if id not define, query getALL
        try{
            $dataResponse = $repository->findBy(["id" => $this->parameters->getData("id")]);

            if(!empty($dataResponse)) {
                //set user's validated fields
                $user = $dataResponse[0];
                foreach( ["firstname", "lastname", "phone", "mobile"]
                         as $field ) {
                    if($this->parameters->getData($field) !== false ) {
                        $setter = 'set'.ucfirst($field);
                        $user->$setter($this->parameters->getData($field));
                    }
                }

                $this->entityManager->flush();
                $user = $this->fileHandler->loadPicture($user);

            }

            //final response
            return $this->responseHandler->successResponse([$user]);

        }catch(Exception $e){
            $this->logger->logError($e,$this->getUser(),"error" );
            return $this->responseHandler->serverErrorResponse($e, "An error occured");
        }
    }
}
