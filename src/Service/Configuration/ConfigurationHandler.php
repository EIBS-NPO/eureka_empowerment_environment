<?php


namespace App\Service\Configuration;


use App\Entity\GlobalPropertyAttribute;
use App\Entity\User;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectRepository;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Class ConfigurationHandler
 * Service that provides easy access to configuration values stored in database as GlobalPropertyAttribute objects.
 * @package App\Service
 * @author Antoine Alexandre <antoine@antoinealexandre.eu>
 */
class ConfigurationHandler
{
    /**
     * Entity Manager object to access persistence layer
     * @var ManagerRegistry
     */
    private $entityManager;

    /**
     * Repository to access Global Property Attribute
     * @var ObjectRepository
     */
    private $GPARespository;

    /**
     * Repository to access Users
     * @var ObjectRepository
     */
    private $UserRepository;

    /**
     * ConfigurationHandler constructor.
     * @param ManagerRegistry $entityManager
     */
    public function __construct(ManagerRegistry $entityManager)
    {
        $this->entityManager = $entityManager;
        $this->GPARespository = $this->entityManager->getRepository(GlobalPropertyAttribute::class);
        $this->UserRepository = $this->entityManager->getRepository(User::class);
    }

    /**
     * Access the current configuration properties for a given user. Return both Global and User level configuration properties. If user is not specify, the global configuration will be returned.
     * @param UserInterface|null $user
     * @return array
     */
    public function getConfig(UserInterface $user = null) : array
    {
        $globalConfig = $this->GPARespository->findBy(["scope"=>'GLOBAL']);

        //If user is not specify, return the global configuration
        if (!$user) {
            return $globalConfig;
        }
        $userConfig = $user->getGPA();

        $applicableConf = array();

        foreach ($userConfig as $value) {
            $applicableConf[$value->getPropertyKey()] = $value;
        }

        foreach ($globalConfig as $globalValue) {
            $global = TRUE;
            foreach ($userConfig as $userValue) {
                if ($userValue->getPropertyKey() == $globalValue->getPropertyKey()) {
                    $global = FALSE;
                    break;
                }
            }
            if ($global) {
                $applicableConf[$globalValue->getPropertyKey()] = $globalValue;
            }
        }

        return $applicableConf;
    }

    /**
     * Get a specific key value for a given user. If user is null, the method will return the GLOBAL value.
     * @param string $propertyKey
     * @param UserInterface|null $user
     * @return string|null
     */
    public function getValue(string $propertyKey, UserInterface $user = null) : ?string
    {
        if ($user == null) {
            $config = $this->GPARespository->findBy(["scope"=>'GLOBAL']);
            $tmp = array();
            foreach ($config as $key=>$value) {
                $key = $value->getPropertyKey();
                $tmp[$key] = $value;
            }
            $config = $tmp;
        } else {
            $config = $this->getConfig($user);
        }
        return $config[$propertyKey]->getPropertyValue();
    }
}