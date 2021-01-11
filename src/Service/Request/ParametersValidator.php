<?php


namespace App\Service\Request;


use App\Entity\Organization;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class ParametersValidator
{
    private ValidatorInterface $validator;

    private EntityManagerInterface $entityManager;

    private array $paramIgnore = [];

    /**
     * description of paramters required for the request
     * @var array
     */
    private array $paramRequire = [];


    private array $paramRequest = [];

    private $classInstance;

    /**
     * ErrorsList for throw Exception
     * @var array
     */
    private array $errors = [];

    public function __construct(ValidatorInterface $validator, EntityManagerInterface $entityManager){
        $this->validator = $validator;
        $this->entityManager = $entityManager;
    }

    /*private function isRequirePresent(){
        foreach($this->paramRequire as $paramName => $paramType){
            if(!array_key_exists($paramName, $this->paramRequest)){
                $errors[] = "Param missed : $paramName : $paramType";
            }
        }
    }

    private function isParamValid(){
        if(gettype($this->paramRequire[$paramName]) !== $paramType ){
            $errors[] = "wrong type parameters for $paramName, given :" . gettype($this->paramRequire[$paramName]) . ", expected : $paramType";
        }
    }

    public function setParamIgnore(array $params){
        $this->paramIgnore = $params;
    }

    public function setParamRequire(array $params){
        $this->paramRequire = $params;
    }

    public function setEntityClass($instance){
        $this->classInstance = $instance;
    }*/

    //todo the following private methods will be moved in the future in a service: parametersValidator
    /**
     * @param $email
     * @return bool
     * @throws Exception
     */
    private function checkUniqueEmail($email) {
        $res = true;
        try{
            $userTest = $this->entityManager->getRepository(User::class)->findBy(["email" => $email]);
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
     * @param String $className
     * @param array $fields
     * @param bool $required
     * @param array $data
     * @return array
     * @throws Exception
     */
    public function fieldsValidation(String $className, array $fields, bool $required, array $data) : array{
        //todo hand object Field
        $object = $this->instanceClass($className);
        $violationsList = [];
        foreach($fields as $field){
            $violations = [];
            if(!isset($data[$field]) && $required){
                $data[$field] = null;
            }
            if(isset($data[$field])){
                $violations = $this->validator->validatePropertyValue($object, $field, $data[$field]);
            }

            if($field == "email" && isset($data["email"]) && count($violations) == 0){
                if($this->checkUniqueEmail($data['email']) == false){
                    $violationsList = array_merge(
                        $violationsList,
                        ["email" => "this email already exist in database for user account"]
                    );
                }
            }

            if(count($violations) > 0 ){
                foreach($violations as $violation){
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
        switch(ucfirst($className)){
            case "User":
                $object  = new User();
                break;
            case "Organization":
                $object = new Organization();
                break;
            default :
                $object = null;
        }
        return $object;
    }
}