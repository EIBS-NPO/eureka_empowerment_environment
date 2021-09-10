<?php

namespace App\Controller\admin;

use App\Entity\User;
use App\Exceptions\ViolationException;
use App\Services\FileHandler;
use App\Services\LogService;
use App\Services\Request\ParametersValidator;
use App\Services\Request\RequestParameters;
use App\Services\Request\ResponseHandler;
use App\Services\Security\RequestSecurity;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class UserAdminController
 * @package App\Controller
 * @Route("/admin/user", name="admin")
 */
class UserAdminController extends AbstractController
{
    private RequestParameters $parameters;
    private ResponseHandler $responseHandler;
    private ParametersValidator $validator;
    protected EntityManagerInterface $entityManager;
    protected FileHandler $fileHandler;
    private LogService $logger;

    /**
     * OrgController constructor.
     * @param RequestParameters $requestParameters
     * @param ResponseHandler $responseHandler
     * @param ParametersValidator $validator
     * @param EntityManagerInterface $entityManager
     * @param FileHandler $fileHandler
     * @param LogService $logger
     */
    public function __construct( RequestParameters $requestParameters, ResponseHandler $responseHandler, ParametersValidator $validator, EntityManagerInterface $entityManager, FileHandler $fileHandler, LogService $logger)
    {
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
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
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
                $dataResponse = [$user];
            }

            //final response
            return $this->responseHandler->successResponse($dataResponse);

        }catch(Exception $e){
            $this->logger->logError($e,$this->getUser(),"error" );
            return $this->responseHandler->serverErrorResponse($e, "An error occured");
        }
    }

    /**
     * @param Request $request
     * @return Response
     * @Route("/active", name="_activation", methods="put")
     */
    public function activation(Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        // recover all data's request
        $this->parameters->setData($request);

        //check if required params exist
        try{ $this->parameters->hasData(["id"]); }
        catch(ViolationException $e) {
            $this->logger->logError($e, $this->getUser(), "error");
            return $this->responseHandler->BadRequestResponse($e->getViolationsList());
        }

        $repository = $this->entityManager->getRepository(User::class);
        try{
            $userData = $repository->findBy(["id" => $this->parameters->getData("id") ]);
            if(!empty($userData)) {
                $user = $userData[0];
                if($user->getRoles()[0] === "ROLE_USER"){
                    $user->setRoles([""]);
                }
                else {$user->setRoles(["ROLE_USER"]);}

                //persist updated user
                $this->entityManager->flush();

            }else {
                $this->logger->logInfo("user with id : ". $this->parameters->getData("id") ." not found" );
                return $this->responseHandler->notFoundResponse();
            }
        }catch(Exception $e){
            $this->logger->logError($e,$this->getUser(),"error" );
            return $this->responseHandler->serverErrorResponse($e, "An error occured");
        }

        $dataResponse = [$user->getRoles()[0]];
        return $this->responseHandler->successResponse($dataResponse);
    }
}
