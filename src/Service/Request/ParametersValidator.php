<?php


namespace App\Service\Request;


use App\Entity\Organization;
use App\Entity\Project;
use App\Entity\User;
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
     * @return array
     * @throws Exception
     */
    public function getViolations($object = null) :array{
        if($object === null){
            $object = new $this->className();
        }

        $violations = [];
        try {
            if($this->requiredFields != null && count($this->requiredFields) > 0){
                $violations = $this->fieldsValidation($object, $this->requiredFields, true, $this->paramRequest);
            }
            if($this->optionalFields != null && count($this->optionalFields) > 0){
                $violations = array_merge($violations, $this->fieldsValidation($object, $this->optionalFields, false, $this->paramRequest));
            }
        } catch (Exception $e) {
            //todo error cannot create metadata?
            throw new Exception($e->getMessage(), $e->getCode());
        }
        return $violations;
    }

    /**
     * @param $object
     * @param array $fields
     * @param bool $required
     * @param array $data
     * @return array
     * @throws Exception
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

    /**
     * @param array $criterias
     * @return bool
     */
    /*public function hasAllCriteria(array $criterias) :bool
    {
        //todo faire retourner une violation plutot qu'une reponse, c'est le CommonController qui construit les reponses
        foreach($criterias as $criteria){
            if(!isset($this->dataRequest[$criteria])){
                $this->logger->info(Response::HTTP_NOT_FOUND . " | missing parameter: " . $criteria);
                $this->response =  new Response(
                    json_encode(["error" => "missing parameter : " . $criteria . " is required "]),
                    Response::HTTP_BAD_REQUEST,
                    ["Content-Type" => "application/json"]
                );
                return false;
            }
        }
        return true;
    }*/

    /**
     * @param String $className
     * @return Organization|User|null
     */
    /*private function instanceClass(String $className){
        switch($className){
            case "App\Entity\User":
                $object  = new User();
                break;
            case "App\Entity\Organization":
                $object = new Organization();
                break;
            case "App\Entity\Project":
                $object = new Project();
                break;
            default :
                $object = null;
        }
        return $object;
    }*/
}