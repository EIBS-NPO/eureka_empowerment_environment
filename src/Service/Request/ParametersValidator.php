<?php


namespace App\Service\Request;


use App\Exceptions\ViolationException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class ParametersValidator
{
    private ValidatorInterface $validator;

    private RequestParameters $paramRequest;

    private ?array $requiredFields = [];

    private ?array $optionalFields = [];

    private String $className;

    private array $violations = [];



    /**
     * ParametersValidator constructor.
     * @param ValidatorInterface $validator
     * @param RequestParameters $requestParameters
     */
    public function __construct(ValidatorInterface $validator, RequestParameters $requestParameters){
        $this->validator = $validator;
        $this->paramRequest = $requestParameters;
    }

    /**
     * @param array|null $requiredFields
     * @param array|null $optionalFields
     * @param $className
     */
    public function initValidator(?array $requiredFields, ?array $optionalFields, $className ) :void{
        $this->requiredFields = $requiredFields;
        $this->optionalFields = $optionalFields;
        $this->className = $className;
    }

    /**
     * @param $object
     * @param $fields
     * @throws ViolationException
     */
//    public function validObject($object, $fields){
//        foreach ($fields as $field){
//            $getter = "get".ucfirst($field);
//            $violations = $this->validator->validatePropertyValue($object, $field, $object->$getter());
//            if(count($violations)> 0 ){
//                $this->violations = array_merge($this->violations,  $violations);
//            }
//        }
//        if(count($this->violations)){
//            throw new ViolationException($this->violations);
//        }
//    }

    /**
     * @param null $object
     * @return void
     */
    public function getViolations($object = null) :void {
        if($object === null){
            $object = new $this->className();
        }

        //check required fields
        if($this->requiredFields != null && count($this->requiredFields) > 0){
            $this->fieldsValidation($object, $this->requiredFields, true, $this->paramRequest->getAllData());
        }

        //check optional fields
        if($this->optionalFields != null && count($this->optionalFields) > 0){
            $this->fieldsValidation($object, $this->optionalFields, false, $this->paramRequest->getAllData());
        }
    }

    /**
     * @param $object
     * @param array $fields
     * @param bool $required
     * @param array $data
     * @return void
     */
    public function fieldsValidation($object, array $fields, bool $required, array $data) : void {
        foreach($fields as $field){
            $violations = [];

            //need to throw violation if a required param is missing
            if(!isset($data[$field]) && $required){
                $data[$field] = "";
            }

            if(isset($data[$field])){
                $violations = $this->validator->validatePropertyValue($object, $field, $data[$field]);
            }

            if(count($violations) > 0 ){
                foreach($violations as $violation){
                    $this->violations = array_merge(
                        $this->violations,
                        [$violation->getPropertyPath() => $violation->getMessage()]
                    );
                }
            }
        }
    }

    /**
     * @param $requiredFields
     * @param $optionalFields
     * @param $className
     * @throws ViolationException
     */
    public function isInvalid($requiredFields, $optionalFields, $className)
    {
        $this->initValidator($requiredFields, $optionalFields, $className);

        $this->getViolations();

        if(count($this->violations) > 0 ){
            throw new ViolationException($this->violations);
        }
    }
}