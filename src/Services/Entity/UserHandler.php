<?php

namespace App\Services\Entity;

use App\Entity\Activity;
use App\Entity\GlobalPropertyAttribute;
use App\Entity\Interfaces\PictorialObject;
use App\Entity\Project;
use App\Entity\User;
use App\Exceptions\BadMediaFileException;
use App\Exceptions\NoFoundException;
use App\Exceptions\PartialContentException;
use App\Exceptions\ViolationException;
use App\Repository\UserRepository;
use App\Services\FileHandler;
use App\Services\Mailer\MailHandler;
use App\Services\Request\ParametersValidator;
use App\Services\Security\SecurityHandler;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use RandomLib\Factory;
use SecurityLib\Strength;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class UserHandler {

    const PICTURE_DIR = '/pictures/User';

    private EntityManagerInterface $entityManager;
    private UserRepository $orgRepo;
    private FileHandler $fileHandler;
    private FollowingHandler $followingHandler;
    private ParametersValidator $validator;
    private SecurityHandler $securityHandler;
    private UserRepository $userRepo;
    private AddressHandler $addressHandler;
    private OrgHandler $orgHandler;

    /**
     * @param EntityManagerInterface $entityManager
     * @param FileHandler $fileHandler
     * @param FollowingHandler $followingHandler
     * @param OrgHandler $orgHandler
     * @param ParametersValidator $validator
     * @param UserRepository $userRepo
     * @param AddressHandler $addressHandler
     * @param SecurityHandler $securityHandler
     */
    public function __construct(EntityManagerInterface $entityManager, FileHandler $fileHandler, FollowingHandler $followingHandler, OrgHandler $orgHandler, ParametersValidator $validator, UserRepository $userRepo, AddressHandler $addressHandler, SecurityHandler $securityHandler)
    {
        $this->entityManager = $entityManager;
        $this->userRepo = $userRepo;
        $this->fileHandler = $fileHandler;
        $this->followingHandler = $followingHandler;
        $this->orgHandler = $orgHandler;
        $this->validator = $validator;
        $this->addressHandler = $addressHandler;
        $this->securityHandler = $securityHandler;
    }

    /**
     * @throws NoFoundException
     */
    public function getUsers(?UserInterface $user, $params, bool $withNotFound=false): array
    {
        $dataResponse =[];
        if(isset($params["access"])){
            switch($params["access"]){
                case "owned":
                    if($user !== null){$dataResponse[] = $this->userRepo->findOneBy(["email" => $user->getUsername()]);}
                    break;
                case "unConfirmed":
                    if(isset($params["admin"])){$dataResponse = $this->userRepo->findAllUnconfirmed();}
                    break;
                case "disabled":
                    if(isset($params["admin"])){$dataResponse = $this->userRepo->findAllDisabled();}
                    break;
                case "search":
                    $criterias = $this->getSearchCriterias($params);
                    $dataResponse = $this->userRepo->search($criterias);
                    break;
                /*case "byFollowinProject":
                    $dataResponse = $this->userRepo->findFollowersByProject($params["followProject"]);
                    break;*/
               /* case "byProject":
                    break;
                case "byOrg":
                    break;*/
                case "all":
                default:
                    $dataResponse = $this->userRepo->findAll();

            }
        }

        if($withNotFound && isset($params["id"]) && !isset($dataResponse[0])){
            throw new NoFoundException("user not found");
        }

        foreach($dataResponse as $userData){
            $userData->setIsConfirmed($this->isConfirmed($userData));

            if(is_null($user) || ($params["access"] !== "owned" && !isset($params["admin"]))){
                $userData->setGlobalPropertyAttributes(new ArrayCollection());
            }
        }

        return $dataResponse;
    }

    /**
     * @throws ViolationException|BadMediaFileException
     */
    public function create($params): User
    {
      //  $this->mailer->sendEmail();
        //check params Validations
        $this->validator->isInvalid(
            ["email", "firstname", "lastname", "password"],
            ["phone", "mobile"],
            User::class);

        //create user object && set validated fields
        $user = new User();
        $this->setUser(null, $user, $params);
        //hash password
        $user->setPassword($this->securityHandler->hashPassword($user));
        //initiate role USER
        $user->setRoles(["ROLE_USER"]);

        $user = $this->add_GPA_activationToken($user);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        //final response
        return $user;
    }


    /**
     * @param UserInterface|null $currentUer
     * @param User $user
     * @param $params
     * @return User
     * @throws PartialContentException
     * @throws ViolationException
     */
    public function updateUser(?UserInterface $currentUer, User $user, $params): User
    {
        try{
            //check params Validations
            $this->validator->isInvalid(
                [],
                ["email", "firstname", "lastname", "phone", "mobile"],
                User::class);

            $user = $this->setUser($currentUer, $user, $params);

            //todo controle owner or admin
            //handle optional address
            if(isset($params["address"]) ) $user = $this->addressHandler->putAddress($user, $params);

            //handle followingActivity
            if(isset($params["followActivity"])) $user = $this->putFollowingActivity($user, $params["followActivity"]);

            //handle followingProject
            if(isset($params["followProject"])) $this->followingHandler->putFollower($params["followProject"], $user);

            //handle assigningProject
            if(isset($params['assigningProject'])) $this->followingHandler->putAssigned($params["assigningProject"], $user);

            //handle assigning in org membership
            if(isset($params["memberOf"])) $this->orgHandler->putMember($params["memberOf"], $user);

        }catch(FileException | BadMediaFileException $e){
            throw new PartialContentException([$user], $e->getMessage());
        } finally {
            $this->entityManager->flush();
        }

    return $user;
    }

    /**
     * if PictureFile is null, delete the oldPicture, else save the new.
     * @param User $user
     * @param $pictureFile
     * @return PictorialObject
     * @throws BadMediaFileException
     */
    public function putPicture(User $user, $pictureFile): PictorialObject
    {
        return $this->fileHandler->uploadPicture(
            $user,
            self::PICTURE_DIR,
            $pictureFile === "null" ? null : $pictureFile
        );
    }

    /**
     * set a user account as active
     * @throws NoFoundException
     */
    public function activation($activationToken):void {

        $user = $this->userRepo->findByActivationToken($activationToken);
        if(!isset($user[0])){
            throw new NoFoundException("User not found");
        }
        $user = $user[0];

       /* //check if it's an activation after changeEmail
        $changeEmailGPA = $user->getGPA("user.email.change");
        if(!$changeEmailGPA->isEmpty()){
            $user->setEmail($changeEmailGPA[0]->getValue());
            $this->entityManager->persist($user);
            $this->entityManager->remove($changeEmailGPA[0]);
        }*/

        //delete activation_token
        $gpa = $user->getGpa("user.token.activation")[0];
        $user->removeGlobalPropertyAttribute($gpa);
        $this->entityManager->remove($gpa);
        $this->entityManager->flush();
    }

    /**
     * @param array $params
     * @throws NoFoundException | UnauthorizedHttpException
     */
    public function resetPassword(array $params) :void {

        //check match newPassword and confirmPassword
        if($params['newPassword'] !== $params['confirmPassword']) {
            throw new BadRequestException("new password not confirmed");
        }
        $userId = explode("U", $params["resetCode"])[0];

      //  $user = $this->getUsers(null, ["access"=>"search", "id"=>$userId], true)[0];
        $user = $this->entityManager->getRepository(User::class)->find($userId);
        if(is_null($user)) throw new NoFoundException("User not found");;

        $gpa = $user->getGPA("user.token.resetPassword")[0];

        if($gpa->getPropertyValue()[0] !== $params["resetPasswordToken"] || $gpa->getPropertyValue()[1] !== $params["resetCode"]){
            throw new UnauthorizedHttpException("bad credentials");
        }

        $user->setPassword($params["newPassword"]);
        $user->setPassword($this->securityHandler->hashPassword($user));

        $this->entityManager->flush();

        $this->entityManager->remove($gpa);
        $this->entityManager->flush();
    }

    /**
     * set for an Organisation object the attributes passed in $attributes array
     * @param UserInterface|null $currentUser
     * @param User $user
     * @param array $attributes
     * @return User with attributes passed
     * @throws BadMediaFileException
     */
    private function setUser(?UserInterface $currentUser, User $user, array $attributes): User {
        foreach( ["email", "firstname", "lastname", "password", "phone", "mobile", "pictureFile", "roles"
                 ] as $field ) {

            if (isset($attributes[$field])) {
                if(preg_match('(^email|^pictureFile$|^roles$)', $field))
                {
                    if($field === "roles") {
                        if($currentUser->getRoles()[0] === "ROLE_ADMIN"){
                            if($attributes[$field] === "null")$attributes[$field] = "";
                            $user->setRoles([$attributes[$field]]);
                        }
                    }

                    //handle optional picture
                    if($field === "pictureFile") {
                        $pictureFile = $attributes[$field];
                        $this->putPicture($user, $pictureFile);
                    }

                    //handle change Email
                    if($field === "email") {
                        $user->setEmail($attributes[$field]);
                        $this->add_GPA_activationToken($user);
                        $this->entityManager->persist($user);

                    }

                }else{
                    if($attributes[$field] === "null")$attributes[$field] = null;
                    $setter = 'set' . ucfirst($field);
                    $user->$setter($attributes[$field]);
                }
            }
        }
        return $user;
    }

    public function withPictures(array $users) : array {
        //download picture
        foreach($users as $key => $user){
                $dataResponse[$key] = $this->fileHandler->loadPicture($user);
        }
        return $users;
    }

    private function add_GPA_activationToken(User $user): User
    {
        //make a new gpa only if user does not have one yet
        if($user->getGPA("user.token.activation")->isEmpty()){
            $gpa = new GlobalPropertyAttribute();
            $gpa->setPropertyKey("user.token.activation")
                ->setDescription("a unique token to send by email to confirm the email account")
                ->setScope("USER")
                ->setPropertyValue([md5(uniqid())]);
            $gpa->setUser($user);
            $user->addGlobalPropertyAttribute($gpa);

            $this->entityManager->persist($gpa);
        }
        return $user;
    }

    public function add_GPA_resetPassword(User $user): User
    {

        $userGPA = $user->getGPA("user.token.resetPassword");
        if($userGPA->isEmpty()){
            $gpa = new GlobalPropertyAttribute();
            $gpa->setPropertyKey("user.token.resetPassword")
                ->setDescription("to renew the password: the first value is a unique token to secure the link send by email, the second value is a secure password. it must be send in the body of the email for secure the transaction with the mail receiver structure is userId+U+password")
                ->setScope("USER")
                ->setPropertyValue([$this->generateUniquePassword(32),$user->getId()."U".$this->generateUniquePassword(8)]);
            $gpa->setUser($user);
            $user->addGlobalPropertyAttribute($gpa);

            $this->entityManager->persist($gpa);
            $this->entityManager->flush();
        }

        return $user;
    }

    /**
     * @return string
     * generate a unique String of 8bytes, the "U" char is exclude of the result.
     */
    private function generateUniquePassword($length): string
    {
        $factory = new Factory();
        $generator = $factory->getLowStrengthGenerator();
        return $generator->generateString($length, "0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTVWXYZ");
    }

    /**
     * @param User $user
     * @return bool
     */
    private function IsConfirmed(User $user): bool
    {
        $res = true;
        if(!($user->getGPA("user.token.activation"))->isEmpty()){
            $res = false;
        }
        return $res;
    }

    private function putFollowingActivity(User $user, Activity $activity): User
    {
        if($user->getFollowingActivities()->contains($activity)){
            $user->removeFollowingActivity($activity);
        }else{
            $user->addFollowingActivity($activity);
        }
   //     $this->entityManager->persist($activity);
        return $user;
    }

    private function getSearchCriterias(Array $params): array
    {
        $criterias = [];
        foreach( ["id", "email", "firstname", "lastname", "phone", "mobile",
            "followingActivity_id", "followingProject_object", "followingProject_isFollowing", "followingProject_isAssigning", "memberOf_id"
                     ] as $field )
        {
            if(isset($params[$field])){
                //force boolean type
                if($params[$field] === "true") $params[$field] = true;
                if($params[$field] === "false") $params[$field] = false;

                $criterias[$field] = $params[$field];
            }
        }

        return $criterias;
    }
}