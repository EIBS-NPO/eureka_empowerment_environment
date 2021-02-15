<?php

namespace App\Service\Security;

use App\Exceptions\SecurityException;
use App\Service\Configuration\ConfigurationHandler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Class RequestSecurity
 * @package App\Service\Security
 * @author Antoine ALEXANDRE <antoine@antoinealexandre.eu>
 */
class RequestSecurity
{
    /**
     * @var array Current configuration of the app. Based on user if provided
     */
    private $config;

    /**
     * @var ConfigurationHandler ConfigurationHandler object to manipulate the configuration and retrieve configuration keys easily.
     */
    private $configHandler;

    private $forbiddenStrings;

    /**
     * RequestSecurity constructor.
     * @param ConfigurationHandler $configurationHandler
     * @param UserInterface|null $user
     */
    public function __construct(ConfigurationHandler $configurationHandler, UserInterface $user = null)
    {
        $this->config = $configurationHandler->getConfig($user);
        $this->configHandler = $configurationHandler;
        $this->forbiddenStrings = explode(',', $this->configHandler->getValue("security.fields.forbiddenstrings"));

        //Trim each forbidden string from spaces
        foreach ($this->forbiddenStrings as $key=>$value)
        {
            $this->forbiddenStrings[$key] = trim($value);
        }
    }

    /**
     * @param Request $request The request you want to clean from possible Cross site scripting attack.
     * @return Request Return a request object containing the right data.
     * @throws SecurityException Throw an Exception containing the details of the error
     */
    public function cleanXSS(Request $request) : Request
    {
        // TODO : Retrieve the parameters of the query

        //Clean URL parameters
        $param = $request->query->getIterator()->getArrayCopy();
        $urlParams = [];
        foreach ($param as $key => $value)
        {
            foreach ($this->forbiddenStrings as $forbiddenString) {
                if (!(strpos($value, $forbiddenString) === false)) {
                    throw new SecurityException('Forbidden strings has been found in URL parameters, the string was : '.htmlentities($value));
                }
            }

            $urlParams[$key] = htmlentities($value);
        }

        //Clean Body parameters
        $param = $request->request->getIterator()->getArrayCopy();
        $bodyParams = [];
        foreach ($param as $key => $value)
        {
            foreach ($this->forbiddenStrings as $forbiddenString) {
                if (!(strpos($value, $forbiddenString) === false)) {
                    throw new SecurityException('Forbidden strings has been found in body parameters, the string was : '.htmlentities($value));
                }
            }
            $bodyParams[$key] = htmlentities($value);
        }

        //Search for forbidden strings in raw data
        $raw = $request->getContent();
        if($request->getContentType() === "form"){
            $raw = urldecode($raw);
        }

        foreach ($this->forbiddenStrings as $forbiddenString) {
            if (!(strpos($raw, $forbiddenString) === false)) {
                throw new SecurityException('Forbidden strings has been found in body parameters, the string was : '.htmlentities($raw));
            }
        }

        return $request->duplicate($urlParams, $bodyParams);
    }
}