<?php


namespace App\Event\Listener;


use App\Exceptions\SecurityException;
use App\Services\LogService;
use App\Services\Request\ResponseHandler;
use App\Services\Security\RequestSecurity;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class AutoCleanXss implements EventSubscriberInterface
{
    private RequestSecurity $requestSecurity;
    private LogService $logger;
    private ResponseHandler $responseHandler;

    /**
     * @param RequestSecurity $requestSecurity
     * @param LogService $logger
     * @param ResponseHandler $responseHandler
     */
    public function __construct(RequestSecurity $requestSecurity, LogService $logger, ResponseHandler $responseHandler)
    {
        $this->requestSecurity = $requestSecurity;
        $this->logger = $logger;
        $this->responseHandler = $responseHandler;
    }


    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::REQUEST => ['cleanXSS', 255]
        ];
        // TODO: Implement getSubscribedEvents() method.
    }

    /**
     * @param RequestEvent $requestEvent
     * @return Request|void
     */
    public function cleanXSS(RequestEvent $requestEvent)
    {
        $request = $requestEvent->getRequest();
        try{
            return $this->requestSecurity->cleanXSS($request);
        }
        catch(SecurityException $e) {
            //currentUser is null, don't know how catch currentUser here.
            //so i added clientIp
            $this->logger->logError($e, null, "warning", $request->getClientIp());
            $requestEvent->setResponse($this->responseHandler->forbidden());
        }
    }
}