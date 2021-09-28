<?php

namespace App\Services\Entity;

use App\Entity\Interfaces\PictorialObject;
use App\Entity\Organization;
use App\Exceptions\BadMediaFileException;
use App\Exceptions\NoFoundException;
use App\Exceptions\PartialContentException;
use App\Exceptions\ViolationException;
use App\Repository\OrganizationRepository;
use App\Services\FileHandler;
use App\Services\Request\ParametersValidator;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Class OrgHandler
 * @package App\Services\Entity
 */
class OrgHandler {

    const PICTURE_DIR = '/pictures/Organization';

    private EntityManagerInterface $entityManager;
    private OrganizationRepository $orgRepo;
    private FileHandler $fileHandler;
    private FollowingHandler $followingHandler;
    private ParametersValidator $validator;
    private AddressHandler $addressHandler;

    public function __construct(EntityManagerInterface $entityManager, FileHandler $fileHandler, AddressHandler $addressHandler, FollowingHandler $followingHandler, ParametersValidator $validator, OrganizationRepository $orgRepo)
    {
        $this->entityManager = $entityManager;
        $this->orgRepo = $orgRepo;
        $this->fileHandler = $fileHandler;
        $this->addressHandler = $addressHandler;
        $this->followingHandler = $followingHandler;
        $this->validator = $validator;
    }

    /**
     * @param boolean $withNotFound false by default. if passed to true, NotFoundException can be throw for empty results
     * @throws NoFoundException
     * @throws ViolationException
     */
    public function getOrgs(?UserInterface $user, $params, bool $withNotFound=false): array
    {

        //check access
        if (!isset($params["access"]) ||
            ($user === null
                && !preg_match('(^assigned$|^followed$|^owned$|^admin$)', $params["access"])
            )// or if bad access param
            || ($params["access"] === "admin" && $user->getRoles()[0] !== "ROLE_ADMIN") // or it's an access param admin with a no admin user
        ) {
            $params["access"] = 'public';
        }

        if(isset($params['id'])){
            $this->validator->isInvalid(
                ["id"],
                [],
                Organization::class);
            $id = $params['id'];
        }

        switch($params["access"]){
            case "assigned":
                if(isset($id)){
                    $dataResponse = $this->orgRepo->findAssignedById($user->getId(), $params['id']);
                } else{
                    $dataResponse = $this->orgRepo->findAssigned($user->getId());
                }
                break;
            case "followed":
                if(isset($id)){
                    $dataResponse = $this->orgRepo->findFollowedById($user->getId(), $params['id']);
                } else{
                    $dataResponse = $this->orgRepo->findFollowed($user->getId());
                }
                break;
            case "owned":
                if(isset($id)){
                    $dataResponse = $this->orgRepo->findBy(['referent'=>$user, 'id'=>$id]);
                } else{
                    $dataResponse = $this->orgRepo->findBy(["referent"=>$user]);
                }
                break;
            case "admin" :
            case "public":
                if(isset($id)){
                    $dataResponse = $this->orgRepo->findBy($id);
                }else {
                    if(isset($params["partner"])){
                        $dataResponse = $this->orgRepo->findBy(["isPartner" => true]);
                    }else$dataResponse = $this->orgRepo->findAll();
                }
                break;
            default :
                $dataResponse = [];
        }

        if($withNotFound && count($dataResponse) === 0){
            if(isset($id)){
                $msg = "[organization id : ".$id . "]";
            }else {
                $msg ="no organization found";
            }
            if($user !== null){
                $msg .= " for [user : ".$user->getId() . "]";
            }
            throw new NoFoundException($msg);
        }

        //if current user isn't assigned or creator, filter private activities in each project result
        if($params["access"] === "followed") {
            foreach ($dataResponse as $org) {
                if (!$org->isMember($user)) {
                    $org->setActivities($org->getOnlyPublicActivities());
                }
            }
        }

        //only with public resources for public access
        if($params["access"] === "public" ){
            foreach($dataResponse as $project) {
                $project->setActivities($project->getOnlyPublicActivities());
            }
        }

        return $dataResponse;
    }

    /**
     * @param $params
     * @return Organization
     * @throws PartialContentException
     * @throws ViolationException
     */
    public function createOrg($params): Organization
    {
        //check params Validations
         $this->validator->isInvalid(
            ["name", "type", "email", "referent"],
            ["phone", 'description'],
            Organization::class);

        //create org object && set fields
        $org = new Organization();
        $this->setOrg($org, $params);

        //Optional image management without blocking the creation of the entity
        try{
            //handle optionnal address
            if(isset($params["address"])) $org = $this->addressHandler->putAddress($org, $params);

            //handle optionnal picture
            if(isset($params["pictureFile"])){ //can't be null for creating
                $org= $this->fileHandler->uploadPicture($org,self::PICTURE_DIR, $params["pictureFile"]);
            }
        }catch(FileException | BadMediaFileException $e){
            throw new PartialContentException([$org], $e->getMessage());
        } finally {
            //persist
            $this->entityManager->persist($org);
            $this->entityManager->flush();
        }

    return $org;
    }

    /**
     * update an org with attributes in the $params arrayB
     * @param $org Organization the org object that will be updated
     * @param $params array of params can be updated for an org
     *      ["name", "type", "email", "phone", 'description', "pictureFile"]
     *      all params are optionals
     * @return Organization of organization with pictures loaded
     * @throws ViolationException
     */
    public function updateOrg(Organization $org, array $params): Organization
    {
        //check params Validations
        $this->validator->isInvalid(
            [],
            ["name", "type", "email", "phone", 'description'],
            Organization::class);

        $org = $this->setOrg($org, $params);

        try{
            //handle optionnal address
            if(isset($params["address"])) $org = $this->addressHandler->putAddress($org, $params);

            //handle optionnal picture
            if(isset($params["pictureFile"])){ //can't be null for creating
                $org= $this->fileHandler->uploadPicture($org,self::PICTURE_DIR, $params["pictureFile"]);
            }
        }catch(FileException | BadMediaFileException $e){
            throw new PartialContentException([$org], $e->getMessage());
        } finally {
            //persist
            $this->entityManager->persist($org);
            $this->entityManager->flush();
        }


     //   $this->entityManager->flush();

        return $org;
    }

    /**
     * if PictureFile is null, delete the oldPicture, else save the new.
     * @param Organization $org
     * @param $params
     * @return Organization
     * @throws BadMediaFileException
     */
    public function putPicture(Organization $org, $params): PictorialObject
    {
        $org = $this->fileHandler->uploadPicture(
            $org,
            self::PICTURE_DIR,
            $params["pictureFile"] === "null" ? null : $params["pictureFile"]
        );

        $this->entityManager->flush();

        return $org;
    }


    /**
     * set for an Organisation object the attributes passed in $attributes array
     * @param Organization $org
     * @param array $attributes
     * @return Organization with attributes passed
     */
    private function setOrg(Organization $org, array $attributes): Organization {
        foreach( ["name", "type", "email", "referent", "phone", 'description'] as $field ) {
            if (isset($attributes[$field])) {
                $setter = 'set' . ucfirst($field);
                $org->$setter($attributes[$field]);
            }
        }
        return $org;
    }

    /**
     * returns an organization array with loaded images for all orgs components that may have images
     *
     * @param array $orgs array of organizations without loaded pictures
     * @return array
     */
    public function withPictures(Array $orgs): array
    {
        //load picture for each org's components
        foreach($orgs as $key => $org){
            $org= $this->fileHandler->loadPicture($org);
            foreach($org->getActivities() as $activity){
                $activity = $this->fileHandler->loadPicture($activity);
            }
            foreach($org->getProjects() as $project){
                $project = $this->fileHandler->loadPicture($project);
            }
            foreach($org->getMembership() as $member){
                $member = $this->fileHandler->loadPicture($member);
            }
            $dataResponse[$key] = $org;
        }

        return $orgs;
    }

    public function putMember (Organization $org, User $user):Organization {

        //new user is already in this org?
        foreach($org->getMembership() as $member){
            if($member->getId() === $user->getId()){
                //todo remove user
                $org->removeMembership($user);
            }
            else {
                $org->addMembership($user);
                //todo add user
            }

           /*     return $this->responseHandler->BadRequestResponse(["email"=> "user already added into the membership"]);
            }*/
        }

        $this->entityManager->flush();
        return $org;
    }

}