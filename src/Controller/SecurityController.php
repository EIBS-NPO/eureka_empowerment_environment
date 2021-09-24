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

    /*
     * @Route("/login", name="login")
     * @param Request $request
     * @param JwtHandler $jwtManager
     * @return Response
     */
     /* public function login(Request $request,  JwtHandler $jwtManager, JWTTokenAuthenticator $authenticator): Response
     {
         $this->parameters->setData($request);
         $user = new User(
             $this->parameters->getData("email"),
             $this->parameters->getData("password")
         );
         $authenticator->checkCredentials();
            $authenticationSuccessHandler = $this->container->get('lexik_jwt_authentication.handler.authentication_success');
            $res = $authenticationSuccessHandler->handleAuthenticationSuccess($user);
            dd($res);
      //   dd($jwtManager->create($user));
        // $jwtManager->setUserIdentityField();

      //   $authenticationSuccessHandler = $this->container->get('lexik_jwt_authentication.handler.authentication_success');

     //    $authenticationSuccessHandler->handleAuthenticationSuccess($user);

         return $this->responseHandler->successResponse([$jwtManager->create($this->getUser())]);
     }*/

    /*
     * @return Response
     * @Route("/token/refresh", name="refresh_token")
     */
    /*public function refresh_token(): Response
    {
        return $this->responseHandler->successResponse([]);
    }*/

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
