<?php

namespace App\Controller;

use App\Entity\User;
use App\Exceptions\SecurityException;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use App\Service\Security\RequestSecurity;
use App\Service\Request\RequestParameters;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Class UserController
 * @package App\Controller
 */
class UserController extends AbstractController
{
    private RequestSecurity $requestSecurity;

    private RequestParameters $requestParameters;

    private EntityManagerInterface $entityManager;

    private ValidatorInterface $validator;

    /**
     * UserController constructor.
     * @param RequestSecurity $requestSecurity
     * @param RequestParameters $requestParameters
     * @param EntityManagerInterface $entityManager
     * @param ValidatorInterface $validator
     */
    public function __construct(RequestSecurity $requestSecurity, RequestParameters $requestParameters, EntityManagerInterface $entityManager, ValidatorInterface $validator){
        $this->requestSecurity = $requestSecurity;
        $this->requestParameters = $requestParameters;
        $this->entityManager = $entityManager;
        $this->validator = $validator;
    }

    /**
     * @Route("/user/register", name="register", methods="post")
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @param UserPasswordEncoderInterface $encoder
     * @return Response
     */
    public function register(Request $request, EntityManagerInterface $entityManager, UserPasswordEncoderInterface $encoder): Response
    {
        try{
            $request = $this->requestSecurity->cleanXSS($request);
        }catch(SecurityException $exception){
            return new Response(
                json_encode(["success" => false, "error" => "Potential attack has been detected"]),
                Response::HTTP_FORBIDDEN,
                ["Content-Type" => "application/json"]);
        }

        //place all parameters of the request in an array $data
        $data = $this->requestParameters->getData($request);

        //request's required Field
        $requiredFields = ["email","firstname", "lastname", "password", "roles"];

        //create user object
        $user = new User();

        //Validate fields
        try{
            $violationsList = $violationsList = $this->fieldsValidation($user, $requiredFields, true, $data);
        }catch(Exception $e){
            return new Response(
                json_encode(["success" => false, "error" => $e->getMessage()]),
                Response::HTTP_BAD_REQUEST,
                ["Content-Type" => "application/json"]
            );
        }

        //return violations
        if( count($violationsList) > 0 ){
            return new Response(
                json_encode(["success" => false, "error" => $violationsList]),
                Response::HTTP_BAD_REQUEST,
                ["Content-Type" => "application/json"]
            );
        }

        //set user's validated fields
        $user->setFirstname($data['firstname']);
        $user->setLastname($data['lastname']);
        $user->setEmail($data['email']);
        $user->setRoles(["ROLE_USER"]);
        $user->setPassword($data['password']);

        //hash password
        $hash = $encoder->encodePassword($user, $user->getPassword());
        $user->setPassword($hash);

        //persist the new user
        try{
            $entityManager->persist($user);
            $entityManager->flush();
        }catch(Exception $e){
            return new Response(
                json_encode(["success" => false, "error" => $e->getMessage()]),
                $e->getCode(),
                ["Content-Type" => "application/json"]
            );
        }

        //success
        return new Response(
            json_encode(["success" => true]),
            Response::HTTP_OK,
            ["Content-Type" => "application/json"]
        );
    }

    //todo add access Owne (& admin ?)
    /**
     * @Route("/user", name="getUser", methods="get")
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function getUserData(Request $request, EntityManagerInterface $entityManager): Response
    {
        //cleanXSS
        try {
            $request = $this->requestSecurity->cleanXSS($request);
        }catch(\Exception $e) {
            return new Response(
                json_encode(["success" => false, "error" => "Potential attack has been detected"]),
                Response::HTTP_FORBIDDEN,
                ["Content-Type" => "application/json"]);
        }

        //recover parameters of the request in an array $data
        $data = $this->requestParameters->getData($request);

        $userRepository = $entityManager->getRepository(User::class);
        try{
            //verifies the existence of userId or email field for query by criteria
            if(count($data) > 0 && (isset($data['userId']) || isset($data['email']))){
                $userData = $userRepository->findBy(
                    isset($data['userId']) ? ['id' => $data['userId']] : ['email' => $data['email']]
                );
            }else { //otherwise we return all users
                $userData = $userRepository->findAll();
            }
        }
        catch(\Exception $e){
            return new Response(
                json_encode(["success" => false, "error" => $e->getMessage()]),
                $e->getCode(),
                ["Content-Type" => "application/json"]
            );
        }

        //serialize all found userObject
        if (count($userData) > 0 ) {
            foreach($userData as $key => $user){
                $userData[$key] = $user->serialize();
            }
        }
        else {
            return new Response(
                json_encode(["success" => false, "error" => "User Not Found"]),
                Response::HTTP_NOT_FOUND,
                ["Content-Type" => "application/json"]
            );
        }

        return new Response(
            json_encode(["success" => true, "data" => $userData]),
            Response::HTTP_OK,
            ["content-type" => "application/json"]
        );
    }

    /**
     * @Route("/admin/user/delete", methods="delete")
     * @param Request $request
     * @return Response
     */
    public function deleteUser(Request $request):Response
    {
        try{
            $request = $this->requestSecurity->cleanXSS($request);
        }catch(SecurityException $exception){
            return new Response(
                json_encode(["success" => false, "error" => "Potential attack has been detected"]),
                Response::HTTP_FORBIDDEN,
                ["Content-Type" => "application/json"]);
        }

        //recover parameters of the request in an array $data
        $data = $this->requestParameters->getData($request);

        //validation user's id and recover userObject
        try{
            if (!isset($data['id'])) {
                throw new Exception("User id required for update user profil", Response::HTTP_BAD_REQUEST);
            }
            if(!is_numeric($data['id'])){
                throw new Exception("user id must be numeric", Response::HTTP_BAD_REQUEST);
            }

            $user = $this->entityManager->getRepository(User::class)->find($data["id"]);
            if ($user == null) {
                throw new Exception("user not found", Response::HTTP_NOT_FOUND);
            }
        }catch(\Exception $e){
            return new Response(
                json_encode(["success" => false, "error" => $e->getMessage()]),
                $e->getCode(),
                ["Content-Type" => "application/json"]
            );
        }

        //deleting the user
        try{
            $this->entityManager->remove($user);
            $this->entityManager->flush();
        }catch (\Exception $e){
            return new Response(
                json_encode(["success" => false, "error" => $e->getMessage()]),
                $e->getCode(),
                ["Content-Type" => "application/json"]
            );
        }

        //success
        return new Response(
            json_encode(["success" => true, "message" => "The user has been deleted"]),
            Response::HTTP_OK,
            ["Content-Type" => "application/json"]
        );
    }

    //todo own access & admin
    /**
     * @Route("/user/update", methods="put")
     * @param Request $request
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function updateUser(Request $request, EntityManagerInterface $entityManager) : Response
    {
        //cleanXSS
        try{
            $request = $this->requestSecurity->cleanXSS($request);
        }catch(SecurityException $exception){
            return new Response(
                json_encode(["success" => false, "error" => "Potential attack has been detected"]),
                Response::HTTP_FORBIDDEN,
                ["Content-Type" => "application/json"]);
        }

        //recover parameters of the request in an array $data
        $data = $this->requestParameters->getData($request);

        //validation user's id and recover userObject
        try{
            if (!isset($data['id'])) {
                throw new Exception("User id required for update user profil", Response::HTTP_BAD_REQUEST);
            }
            if(!is_numeric($data['id'])){
                throw new Exception("user id must be numeric", Response::HTTP_BAD_REQUEST);
            }

            $user = $entityManager->getRepository(User::class)->find($data["id"]);
            if ($user == null) {
                throw new Exception("user not found", Response::HTTP_NOT_FOUND);
            }
         //   else if(gettype($user) == "array"){ $user = $user[0];}

        }catch(\Exception $e){
            return new Response(
                json_encode(["success" => false, "error" => $e->getMessage()]),
                $e->getCode(),
                ["Content-Type" => "application/json"]
            );
        }

        //validation of optional fields
        $optionalFields = ["email","firstname", "lastname", "phone", "mobile"];
        try{
            $violationsList = $violationsList = $this->fieldsValidation($user, $optionalFields, false, $data);
        }catch(Exception $e){
            return new Response(
                json_encode(["success" => false, "error" => $e->getMessage()]),
                Response::HTTP_BAD_REQUEST,
                ["Content-Type" => "application/json"]
            );
        }

        //return violations
        if( count($violationsList) > 0 ){
            return new Response(
                json_encode(["success" => false, "error" => $violationsList]),
                Response::HTTP_BAD_REQUEST,
                ["Content-Type" => "application/json"]
            );
        }

        //set user's validated fields
        foreach($optionalFields as $field) {
            if (isset($data[$field])) {
                $setter = "set" . ucfirst($field);
                $user->$setter($data[$field]);
            }
        }

        //persist the new user
        try{
            $entityManager->persist($user);
            $entityManager->flush();
        }catch(Exception $e){
            return new Response(
                json_encode(["success" => false, "error" => $e->getMessage()]),
                $e->getCode(),
                ["Content-Type" => "application/json"]
            );
        }

        //success
        return new Response(
            json_encode(["success" => true, "data" => $user->serialize()]),
            Response::HTTP_OK,
            ["Content-Type" => "application/json"]
        );
    }

    /**
     * @param $user
     * @param $role
     */
    public function switchUserRole($user, $role){
        //todo switchUserRole with admin Access
    }

    //todo the following private methods will be moved in the future in a service: parametersValidator
    /**
     * @param $email
     * @return bool
     * @throws Exception
     */
    private function checkUniqueEmail($email) {
     //   if($field == "email" && count($violations) == 0){
            $res = false;
            try{
                $userTest = $this->entityManager->getRepository(User::class)->findBy(["email" => $email]);
            }
            catch(\Exception $e){
                throw new Exception("this email already exist in database for user account", Response::HTTP_BAD_REQUEST);
            }

            if ($userTest === null) {
                $res = true;
            }
            return $res;
      //  }
    }

    //todo the following private methods will be moved in the future in a service: parametersValidator
    /**
     * @param User $user
     * @param array $fields
     * @param bool $required
     * @param array $data
     * @return array
     * @throws Exception
     */
    private function fieldsValidation(User $user, array $fields, bool $required, array $data) : array{
        $violationsList = [];
        foreach($fields as $field){
            $violations = [];
            if(!isset($data[$field]) && $required){
                $data[$field] = null;
            }
            if(isset($data[$field])){
                $violations = $this->validator->validatePropertyValue($user, $field, $data[$field]);
            }

            //unique email validation
            if($field == "email" && isset($data["email"]) && count($violations) == 0){
                $this->checkUniqueEmail($data['email']);
            }

          //  dd($violations);
            if(count($violations) > 0 ){
                foreach($violations as $violation){
                    $violationsList[] = [$violation->getPropertyPath() => $violation->getMessage()];
                }
            }
        }
        return $violationsList;
    }
}
