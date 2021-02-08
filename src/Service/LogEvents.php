<?php


namespace App\Service;


use App\Entity\Events;
use App\Entity\User;
use App\Service\Request\ParametersValidator;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class LogEvents
{
    private EntityManagerInterface $entityManager;

    private LoggerInterface $logger;

    private ParametersValidator $paramValidator;

    /**
     * LogEvents constructor.
     * @param EntityManagerInterface $entityManager
     * @param ParametersValidator $paramValidator
     * @param LogEvents $logger
     */
    public function __construct(EntityManagerInterface $entityManager, ParametersValidator $paramValidator, LoggerInterface $logger){
        $this->entityManager = $entityManager;
        $this->logger = $logger;
        $this->paramValidator = $paramValidator;
    }

    /**
     * @param UserInterface $actor
     * @param int $targetId
     * @param $targetType
     * @param String $description
     */
    public function addEvents(UserInterface $actor, int $targetId, $targetType, String $description) {

        $this->paramValidator->initValidator(
            ["actor","targetType", "target", "description"],
            null,
            Events::class,
            [$actor, $targetType, $targetId, $description]
        );

        try{
            $this->paramValidator->getViolations();
        }catch(Exception $e){
            $this->logger->error($e);
        }

        $event = new Events();
        $event->setActor($actor);
        $event->setTargetType($targetType);
        $event->setTarget($targetId);
        $event->setDescription($description);
        $event->setDate(new DateTime("now"));

        try{
            $this->entityManager->persist($event);
            $this->entityManager->flush();
        }catch(Exception $e){
            $this->logger->error($e);
        }
    }
}