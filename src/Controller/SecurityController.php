<?php

namespace App\Controller;

use App\Exceptions\NoFoundException;
use App\Exceptions\ViolationException;
use App\Services\Entity\UserHandler;
use App\Services\LogService;
use App\Services\Request\RequestParameters;
use App\Services\Request\ResponseHandler;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class SecurityController extends AbstractController
{
    private UserHandler $userHandler;
    private RequestParameters $parameters;
    private ResponseHandler $responseHandler;
    private LogService $logger;

    /**
     * @param UserHandler $userHandler
     * @param RequestParameters $requestParameters
     * @param ResponseHandler $responseHandler
     * @param LogService $logger
     */
    public function __construct(UserHandler $userHandler, RequestParameters $requestParameters, ResponseHandler $responseHandler, LogService $logger)
    {
        $this->userHandler = $userHandler;
        $this->parameters = $requestParameters;
        $this->responseHandler = $responseHandler;
        $this->logger = $logger;
    }

    /**
     * @Route("/login", name="login")
     * @return Response
     */
    public function login(): Response
    {
        return $this->responseHandler->successResponse([]);
    }

     /**
     * @Route("/activation", name="_activation", methods="put")
     * @param Request $request
     * @return Response
     */
    public function activation(Request $request) :Response {
            try{
                // recover all data's request
                $this->parameters->setData($request);
                $this->userHandler->activation($this->parameters->getData("token"));

                return $this->responseHandler->successResponse([]);
            }
            catch(ViolationException | NoFoundException $e) {
                $this->logger->logError($e, $this->getUser(), "error");
                return $this->responseHandler->BadRequestResponse($e->getMessage());
            }
            catch(Exception $e){
                $this->logger->logError($e, $this->getUser(), "error");
                return $this->responseHandler->serverErrorResponse("An error occured");
            }
    }



    /*public function getTokenUser(UserInterface $user, JWTTokenManagerInterface $JWTManager)
    {
        // ...

        return new JsonResponse(['token' => $JWTManager->create($user)]);
    }*/
}
