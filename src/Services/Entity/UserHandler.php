<?php

namespace App\Services\Entity;

use App\Entity\Interfaces\PictorialObject;
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
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
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
    private MailHandler $mailer;

    /**
     * @param EntityManagerInterface $entityManager
     * @param FileHandler $fileHandler
     * @param FollowingHandler $followingHandler
     * @param ParametersValidator $validator
     * @param UserRepository $userRepo
     * @param SecurityHandler $securityHandler
     * @param MailHandler $mailer
     */
    public function __construct(EntityManagerInterface $entityManager, FileHandler $fileHandler, FollowingHandler $followingHandler, ParametersValidator $validator, UserRepository $userRepo, SecurityHandler $securityHandler, MailHandler $mailer)
    {
        $this->entityManager = $entityManager;
        $this->userRepo = $userRepo;
        $this->fileHandler = $fileHandler;
        $this->followingHandler = $followingHandler;
        $this->validator = $validator;
        $this->securityHandler = $securityHandler;
        $this->mailer = $mailer;
    }

    public function getUsers(?UserInterface $user, $params): array
    {

        //check access
    /*    if (!isset($params["access"]) ||
            ($user === null
                && !preg_match('(^self$|^admin$)', $params["access"])
            )// or if bad access param
            || ($params["access"] === "admin" && $user->getRoles()[0] !== "ROLE_ADMIN") // or it's an access param admin with a no admin user
        ) {
            $params["access"] = 'self';
        }*/

        if(isset($params["access"])){
            if(is_int($params["access"])){//if access have an id
                $dataResponse = $this->userRepo->findBy(["id" => $params["access"]]);
            }
            else{ //other access possibility
                switch($params["access"]){
                    case "owned":
                        if($user !== null){
                            $dataResponse[] = $this->userRepo->findOneBy(["email" => $user->getUsername()]);
                        }
                        break;
                    case "byProject":
                        break;
                    case "byOrg":
                        break;
                    case "all":
                            $dataResponse = $this->userRepo->findAll();
                        break;
                    default:
                        $dataResponse = $this->userRepo->findAll();

                }
            }
        }

        return $dataResponse;
    }

    /**
     * @throws ViolationException|UniqueConstraintViolationException
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
        $this->setUser($user, $params);
        //hash password
        $user->setPassword($this->securityHandler->hashPassword($user));
        //initiate role USER
        $user->setRoles(["ROLE_USER"]);
        $user->setActivationToken(md5(uniqid()));

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        //final response
        return $user;
    }


    /**
     * @param User $user
     * @param $params
     * @return array
     * @throws ViolationException
     */
    public function updateUser(User $user, $params): User
    {

        //check params Validations
        $this->validator->isInvalid(
            [],
            ["firstname", "lastname", "phone", "mobile"],
            User::class);

        $user = $this->setUser($user, $params);

        $this->entityManager->flush();

    return $user;
    }

    /**
     * if PictureFile is null, delete the oldPicture, else save the new.
     * @param User $user
     * @param $params
     * @return PictorialObject
     * @throws BadMediaFileException
     */
    public function putPicture(User $user, $params): PictorialObject
    {
        $user = $this->fileHandler->uploadPicture(
            $user,
            self::PICTURE_DIR,
            $params["pictureFile"] === "null" ? null : $params["pictureFile"]
        );

        $this->entityManager->flush();

        return $user;
    }

    /**
     * @throws NoFoundException|TransportExceptionInterface
     */
    public function sendAskActivation($clientDns, $email) :void{
        if(!$email){
            throw new NoFoundException("User not found");
        }
        $user = $this->userRepo->findOneBy(['email' => $email]);

        $this->mailer->sendConfirmEmail($clientDns, $user);
    }

    /**
     * set a user account as active
     * @throws NoFoundException
     */
    public function activation($activationToken):void {

        $user = $this->userRepo->findOneBy(['activationToken' => $activationToken]);

        if(!$user){
            throw new NoFoundException("User not found");
        }

        //delete activation_token
        $user->setActivationToken(null);
        $this->entityManager->persist($user);
        $this->entityManager->flush();
    }
/*
    public function updateUser(User $user, $params) {

            $this->validator->isInvalid(
                [],
                ["firstname", "lastname", "phone", "mobile"],
                User::class);
                $user = $this->setUser($user, $params);


                $this->entityManager->flush();

        //    }

        return [$this->withPictures([$user]),
            'token' => $this->securityHandler->createToken($user)
        ];
    }*/

    /**
     * set for an Organisation object the attributes passed in $attributes array
     * @param User $user
     * @param array $attributes
     * @return User with attributes passed
     */
    private function setUser(User $user, array $attributes): User {
        foreach( ["email", "firstname", "lastname", "password", "phone", "mobile"] as $field ) {
            if (isset($attributes[$field])) {
                $setter = 'set' . ucfirst($field);
                $user->$setter($attributes[$field]);
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

}