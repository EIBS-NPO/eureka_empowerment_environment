<?php


namespace App\Service\Request;


use App\Service\Security\RequestSecurity;

class RequestHandler
{
    protected RequestSecurity $security;
    protected RequestParameters $parameters;
    protected ResponseHandler $response;
    /**
     * RequestHandler constructor.
     * @param RequestSecurity $requestSecurity
     * @param RequestParameters $requestParameters
     * @param ResponseHandler $response
     */
    public function __construct(RequestSecurity $requestSecurity, RequestParameters $requestParameters, ResponseHandler $response)
    {
        $this->security = $requestSecurity;
        $this->parameters = $requestParameters;
        $this->response = $response;
    }


}