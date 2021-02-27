<?php


namespace App\Service\Request;


use App\Entity\Organization;
use App\Entity\Project;
use App\Entity\User;
use App\Exceptions\ViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\HttpFoundation\Response;
use Psr\Log\LoggerInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class ParametersValidator
{
    public ValidatorInterface $validator;

    private EntityManagerInterface $entityManager;

    /**
     * @var array|null
     */
    private ?array $requiredFields = [];

    private ?array $optionalFields = [];

    private array $paramRequest = [];

    private String $className;

    private LoggerInterface $logger;

    /**
     * ParametersValidator constructor.
     * @param ValidatorInterface $validator
     * @param EntityManagerInterface $entityManager
     * @param LoggerInterface $logger
     */
    public function __construct(ValidatorInterface $validator, EntityManagerInterface $entityManager, LoggerInterface $logger){
        $this->validator = $validator;
        $this->entityManager = $entityManager;
        $this->logger = $logger;
    }

    /**
     * @param array|null $requiredFields
     * @param array|null $optionalFields
     * @param $className
     * @param $paramRequest
     */
    public function initValidator(?array $requiredFields, ?array $optionalFields, $className, $paramRequest) :void{
        $this->requiredFields = $requiredFields;
        $this->optionalFields = $optionalFields;
        $this->className = $className;
        $this->paramRequest = $paramRequest;
    }

    public function validObject($object, $fields){
        $violationsList = [];
        foreach ($fields as $field){
            $getter = "get".ucfirst($field);
            $violations = $this->validator->validatePropertyValue($object, $field, $object->$getter());
            if(count($violations)> 0 ){
                $violationsList[] = $violations;
            }
        }
        return $violationsList;
    }

    /**
     * @param null $object
     * @return void
     * @throws ViolationException
     */
    public function getViolations($object = null) :void {
        if($object === null){
            $object = new $this->className();
        }

        $violations = [];

        //check required fileds
        if($this->requiredFields != null && count($this->requiredFields) > 0){
            $violations = $this->fieldsValidation($object, $this->requiredFields, true, $this->paramRequest);
        }

        //check optional fileds
        if($this->optionalFields != null && count($this->optionalFields) > 0){
            $violations = array_merge($violations, $this->fieldsValidation($object, $this->optionalFields, false, $this->paramRequest));
        }

        if( count($violations) > 0 ){
            throw new ViolationException($violations);
        }
    }

    /**
     * @param $object
     * @param array $fields
     * @param bool $required
     * @param array $data
     * @return array
     */
    public function fieldsValidation($object, array $fields, bool $required, array $data) : array{
        $violationsList = [];
        foreach($fields as $field){
            $violations = [];
            if(!isset($data[$field]) && $required){
                $data[$field] = "";
            }
            if(isset($data[$field])){
                $violations = $this->validator->validatePropertyValue($object, $field, $data[$field]);
            }

            if(count($violations) > 0 ){
                foreach($violations as $violation){
                    $this->logger->info($violation);
                    $violationsList = array_merge(
                        $violationsList,
                        [$violation->getPropertyPath() => $violation->getMessage()]
                    );
                }
            }
        }
        return $violationsList;
    }
}