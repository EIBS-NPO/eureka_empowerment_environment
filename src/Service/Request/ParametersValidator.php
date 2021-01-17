<?php


namespace App\Service\Request;


use App\Entity\Organization;
use App\Entity\Project;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class ParametersValidator
{
    private ValidatorInterface $validator;

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

    /**
     * @return array
     * @throws Exception
     */
    public function checkViolations() :array{
        $object = $this->instanceClass($this->className);
        $violations = [];
        try {
            if($this->requiredFields != null && count($this->requiredFields) > 0){
                $violations = $this->fieldsValidation($object, $this->requiredFields, true, $this->paramRequest);
            }
            if($this->optionalFields != null && count($this->optionalFields) > 0){
                array_merge($violations, $this->fieldsValidation($object, $this->optionalFields, false, $this->paramRequest));
            }
        } catch (Exception $e) {
            throw new Exception($e->getMessage(), $e->getCode());
        }

        return $violations;
    }

    //todo the following private methods will be moved in the future in a service: parametersValidator

    /**
     * @param String $fieldName
     * @param $fieldValue
     * @return bool
     * @throws Exception
     */
    private function checkUniqueField(String $fieldName, $fieldValue) {
        $res = true;
        try{
            $userTest = $this->entityManager->getRepository($this->className)->findBy([$fieldName => $fieldValue]);
        }
        catch(\Exception $e){
            throw new Exception($e->getMessage(), $e->getCode());
        }

        if ($userTest != null) {
            $res = false;
        }
        return $res;
    }

    //todo the following private methods will be moved in the future in a service: parametersValidator

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

            if($this->className == "App\Entity\User"){
                if($field == "email" && isset($data["email"]) && count($violations) == 0){
                    if($this->checkUniqueField('email', $data['email']) == false){
                        $this->logger->info("this email already exist in database for user account");
                        $violationsList = array_merge(
                            $violationsList,
                            ["email" => "this email already exist in database for user account"]
                        );
                    }
                }
            }
            if($this->className == "App\Entity\Organization"){
                if($field == "name" && isset($data["name"]) && count($violations) == 0){
                    if($this->checkUniqueField('name', $data['name']) == false){
                        $this->logger->info("this organization's name already exist in database");
                        $violationsList = array_merge(
                            $violationsList,
                            ["name" => "this organization's name already exist in database"]
                        );
                    }
                }
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
     * @param String $className
     * @return Organization|User|null
     */
    private function instanceClass(String $className){
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
    }
}