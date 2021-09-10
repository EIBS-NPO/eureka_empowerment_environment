<?php


namespace App\Services;

use App\Entity\Events;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Class LogService
 * @package App\Services
 */
class LogService
{
    /**
     * Manager registry to access doctrine ; injected by DI
     */
    private EntityManagerInterface $entityManager;

    /**
     * Default logger (ever Symfony's PSR logger or Monolog) ; injected by DI
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * Level of log defined in app's environment. Settings can be changed in .env config files.
     * @var string
     */
    private string $level;


    /**
     * LogService constructor.
     * @param EntityManagerInterface $entityManager
     * @param LoggerInterface $logger
     */
    public function __construct(EntityManagerInterface $entityManager, LoggerInterface $logger){
        $this->entityManager = $entityManager;
        $this->logger = $logger;
        $this->level = $_SERVER['LOG_LEVEL'];
    }


    /**
     * @param UserInterface|null $currentUser
     * @param int $targetId
     * @param String $targetType
     * @param String $description
     */
    public function logEvent(?UserInterface $currentUser, int $targetId, String $targetType, String $description ) {

        if ($currentUser AND get_class($currentUser) == 'App\Entity\User') {
            $uid = $currentUser->getId();
        } else {
            $uid = 'undefined';
            $currentUser = null;
        }

        try {
            $date = new DateTime("now");
        } catch (Exception $exception) {
            $this->logger->warning("An error occurred while trying to recover the date (UID : ".$uid.") : $description");
            $date = null;
        }

        $event = new Events();
        $event->setActor($currentUser);
        $event->setTargetType($targetType);
        $event->setTarget($targetId);
        $event->setDescription($description);
        $event->setDate($date);

        try{
            $this->entityManager->persist($event);
            $this->entityManager->flush();
        }catch(Exception $e){
            $this->logger->warning("An error occurred while trying to persist event. ");
            $date = null;
        }
    }


    /**
     * This method log an exception.
     * @param Exception $exception
     * @param UserInterface|null $currentUser
     * @param null $level
     * @param String|null $userInfo for the case where userInterface is null, it'possible to add userInfo like ClientIp.
     */
    public function logError(Exception $exception, ?UserInterface $currentUser, $level = null, String $userInfo = null): void
    {
        if ($currentUser AND get_class($currentUser) == 'App\Entity\User') {
            $message = $this->level . " | UserID : ".$currentUser->getId()." | ";
        } else {
            if($userInfo === null){
                $message = $this->level . " | UserInfo : undefined | ";
            }else{
                $message = $this->level . " | UserInfo : ".$userInfo." | ";
            }

        }

        $message .= mb_strtoupper($this->level) . " : " . $exception->getMessage() . " : " . $exception->getTraceAsString();

        // Choose what monolog method to use
        if ($level) {
            $monolog = $level;
        } else {
            $monolog = strtolower($this->level);
        }
        $this->logger->$monolog($message);
    }

    public function logInfo($info){
        $this->logger->info($info);
    }
}