<?php

namespace App\Services\Entity;

use App\Entity\Activity;
use App\Entity\ActivityFile;
use App\Entity\Interfaces\PictorialObject;
use App\Entity\Organization;
use App\Entity\Project;
use App\Entity\User;
use App\Exceptions\BadMediaFileException;
use App\Exceptions\NoFoundException;
use App\Exceptions\PartialContentException;
use App\Exceptions\ViolationException;
use App\Repository\ActivityRepository;
use App\Services\FileHandler;
use App\Services\Request\ParametersValidator;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\Exception\NoFileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Security\Core\User\UserInterface;

class ActivityHandler {

    const PICTURE_DIR = '/pictures/Activity';

    private EntityManagerInterface $entityManager;
    private ActivityRepository $activityRepo;
    private FileHandler $fileHandler;
    private FollowingHandler $followingHandler;
    private ParametersValidator $validator;

    public function __construct(EntityManagerInterface $entityManager, FileHandler $fileHandler, FollowingHandler $followingHandler, ActivityRepository $activityRepo, ParametersValidator $validator)
    {
        $this->entityManager = $entityManager;
        $this->activityRepo = $activityRepo;
        $this->fileHandler = $fileHandler;
        $this->followingHandler = $followingHandler;
        $this->validator = $validator;
    }

    /**
     * @throws NoFoundException
     */
    public function getActivities(?UserInterface $user, array $params, bool $withNotFound = false): array{
        //check access
        if ($user === null || //if not connected user
            !isset($params["access"]) // or if no access param defined
            || !preg_match('(|^followed$|^owned$|^admin$)', $params["access"]) // or if bad access param
            || ($params["access"] === "admin" && $user->getRoles()[0] !== "ROLE_ADMIN") // or it's an access param admin with a no admin user
        ) {
            $params["access"] = null;
        }

        switch($params["access"]){
            case "followed":
                if(isset($params['id'])){
                    $dataResponse = $this->activityRepo->findFollowedById($user->getId(), $params['id']);
                } else{
                    $dataResponse = $this->activityRepo->findFollowed($user->getId());
                }
                break;
            case "owned":
                if(isset($params['id'])){
                    $dataResponse = $this->activityRepo->findBy(['creator'=>$user, "id"=>$params["id"]]);
                } else{
                    $dataResponse = $this->activityRepo->findBy(["creator"=>$user]);
                }
                break;
            default : //admin or no access param
                if(isset($params['id'])){
                    $dataResponse = $this->activityRepo->findBy(["id"=>$params['id']]);
                }else {
                    $dataResponse = $this->activityRepo->findAll();
                }
        }

        //check if connected user in followed context have access to activities required
    //    if($params["access"] === "followed"){

    //checks whether the user has access to the resources
            $tab=[];
            foreach ($dataResponse as $activity) {
                if ($this->hasAccess($activity, $user)) {
                    $tab[] = $activity;
                }
            }
            $dataResponse = $tab;
    //    }

        //only return public resources for public access
    /*    if($params["access"] === null ){
            $tab=[];
            foreach($dataResponse as $activity) {
                if($activity->getIsPublic()){
                    $tab[] = $activity;
                }
            }
            $dataResponse = $tab;
        }*/

        if($withNotFound && count($dataResponse) === 0){
            if(isset($id)){
                $msg = "[activity id : ".$id . "]";
            }else {
                $msg ="no activity found";
            }
            if($user !== null){
                $msg .= " for [user : ".$user->getId() . "]";
            }
            throw new NoFoundException($msg);
        }

        if(!is_null($user)){
            foreach($dataResponse as $activity){
                $activity->setIsFollowed($activity->isFollowByUserId($user->getId()));
            }
        }

        return $dataResponse;
    }


    /**
     * Persist Activity with optional file & picture
     * @param UserInterface $user
     * @param array $params
     * @return Activity activity with no load picture
     * @throws PartialContentException
     * @throws ViolationException
     */
    public function create(UserInterface $user, array $params) :Activity
    {
        //check params Validations
         $this->validator->isInvalid(
            ["title", "summary", "postDate", "creator", "isPublic"],
            [],
            Activity::class);


        //create Activity object && set validated fields
        $activity = $this->setActivity($user, new Activity(), $params);
        $activity->setCreator($params["creator"]);

        //Optional image && file management without blocking the creation of the entity
        try{
            if(isset($params["pictureFile"])){ //can't be null for creating
                $activity = $this->fileHandler->uploadPicture($activity,self::PICTURE_DIR, $params["pictureFile"]);
            }

            if(isset($params["file"])){
                $activityFile = new ActivityFile();
                $activityFile->setForActivity($activity);
                $activityFile = $this->uploadFile($activityFile, $params["file"]);
                $activity = $activityFile;
            }

        }catch(FileException | BadMediaFileException $e){
            throw new PartialContentException([$activity], $e->getMessage());
        } finally {
            //persist
            $this->entityManager->persist($activity);
            $this->entityManager->flush();
        }

    return $activity;
    }


    /**
     * @throws ViolationException|PartialContentException
     */
    public function update(UserInterface $user, $activity, $params) :Activity
    {
        try{
        //check params Validations
        $this->validator->isInvalid(
            [],
            ["title", "summary", "isPublic"],
            Activity::class);

        $activity = $this->setActivity($user, $activity, $params);


            //handle optional picture
            if(isset($params["pictureFile"])){ //can't be null for creating
                $activity = $this->putPicture($activity, $params);
            }

            //handle optional File
            if(isset($params["file"])){
                $activity = $this->putFile($activity, $params);
            }

            if(isset($params["follow"])){
                $this->putFollower($user, $activity);
            }

        }catch(FileException | BadMediaFileException $e){
            throw new PartialContentException([$activity], $e->getMessage());
        }finally {
            $this->entityManager->flush();
        }


    return $activity;
    }

    /**
     * @param Activity $activity
     * @param array $params
     * @return PictorialObject
     * @throws BadMediaFileException
     */
    public function putPicture(Activity $activity, array $params): PictorialObject
    {
        //    $this->entityManager->flush();

        return $this->fileHandler->uploadPicture(
            $activity,
            self::PICTURE_DIR,
            $params["pictureFile"] === "null" ? null : $params["pictureFile"]
        );
    }

    /**
     * @throws BadMediaFileException
     * @throws Exception
     */
    public function putFile(Activity $activity, array $params ): Activity
    {

        $file = $params["file"];
        if($file !== null && $file !== "null") { //if have file
            //if need to instanciate a new ActivityFile object
            if(!$this->isActivityFile($activity)) {
                //keep safe for deleting later if upload success
                $activityOld = $activity;

                //create a new activityFile and hydrate it with activity data
                $activity = new ActivityFile();
                $activity->setForActivity($activityOld);
            }

            $this->fileHandler->isAllowedMime($file);

            $activity = $this->uploadFile($activity, $file);

        }else if($this->isActivityFile($activity)){ // if no file and ActivityFile Object
            $activityOld = $activity;
            $activity = new Activity();
            $activity->setFromActivityFile($activityOld);
        }


        //persist the new activity
        $this->entityManager->persist($activity);

        //delete old Activity if necessary
        if(isset($activityOld)) {
            $this->entityManager->remove($activityOld);
        }

   return $activity;
    }

    /**
     * @param ActivityFile $activityFile
     * @param UserInterface|null $user
     * @return string
     * @throws NoFileException | UnauthorizedHttpException
     */
    public function loadFile(ActivityFile $activityFile, UserInterface $user = null): string
    {
        if (!$this->hasAccess($activityFile, $user)) {
            throw new UnauthorizedHttpException("unauthorized file access");
        }

        $completFilename = $activityFile->getUniqId(). '_'. $activityFile->getFilename();
        $file = $this->fileHandler->getFile($completFilename);

        if(!$this->fileHandler->controlChecksum($completFilename, $activityFile->getChecksum())){
            throw new UnauthorizedHttpException("compromised file");
        }

        return $file;
    }

    /**
     * Load picture fo each activities passed in the array parameter
     * @param array $activities
     * @return array
     */
    public function withPictures(array $activities): array {
        //load picture
        foreach ($activities as $key => $activity) {
            if ($activity->getProject() !== null) {
                $activity->setProject($this->fileHandler->loadPicture($activity->getProject()));
            }
            if ($activity->getOrganization() !== null) {
                $activity->setOrganization($this->fileHandler->loadPicture($activity->getOrganization()));
            }
            $activities[$key] = $this->fileHandler->loadPicture($activity);
        }
        return $activities;
    }

    /**
     * @param UserInterface $user
     * @param Activity $activity
     * @param array $attributes
     * @return Activity
     */
    private function setActivity(UserInterface $user, Activity $activity, array $attributes): Activity {
        foreach( ["title", "summary", "postDate", "isPublic", "organization", "project"] as $field ) {
            if (isset($attributes[$field]) ) {
                $canSet = false;
                $setter = 'set' . ucfirst($field);


                if(($field === "organization" || $field === "project")){
                    if(!is_null($activity->getId()) && $activity->getCreator()->getId() === $user->getId() ){//only if Activity isn't a new Object and currentUser is owner

                        if($attributes[$field] === "null"){ $attributes[$field] = null;}

                        //handle org linking if user is member
                        if($field === "organization") {
                            if ((is_null($attributes[$field]) || $attributes[$field]->isMember($user))) {
                                $canSet = true;
                            }
                        }

                        //todo donc si null, n'importe qui peu retirer la relation?
                        //handle project linking is user is assign
                        else if ($field === "project"){
                            if((is_null($attributes[$field] ) || $this->followingHandler->isAssign($attributes[$field], $user))) {
                                $canSet = true;
                            }
                        }

                    }
                }else{ $canSet = true; }

                if($canSet){
                    $activity->$setter($attributes[$field]);
                }
            }
        }
        return $activity;
    }

    /**
     * @throws Exception
     */
    private function uploadFile(ActivityFile $activityFile, UploadedFile $file) :ActivityFile {
        //set file info
        $activityFile->setFilename($this->fileHandler->getOriginalFilename($file).".".$file->guessExtension());
        $activityFile->setFileType($file->getMimeType());
        $activityFile->setSize($file->getSize());
        $activityFile->setUniqId(uniqid());

        //make new filename
        $completName = $activityFile->getUniqId(). '_'. $activityFile->getFilename();

        //upload
        $this->fileHandler->upload( $completName, $file);



        //make new checksum
        $activityFile->setChecksum($this->fileHandler->getChecksum( $completName));

        return $activityFile;
    }

    /**
     * check if an connected user have a reason for access to an activity
     * check if activity is public
     * check if user is the owner of the activity
     * check if user is assigned in project share the activity
     * check if the user is assigned in an organization share the activty
     * @param Activity $activity
     * @param UserInterface|null $user
     * @return bool
     */
    private function hasAccess(Activity $activity, ?UserInterface $user): bool
    {
        $res = false;
        if(!$activity->getIsPublic() && $user !== null){ //if isn't public resource
            if($activity->getCreator()->getId() === $user->getId()){ //if user is creator
                $res = true;
            }
            else if($activity->getProject() !== null){ //if assign into project
                foreach($activity->getProject()->getFollowings() as $following){
                    if($following->getFollower()->getId() === $user->getId() && $following->getIsAssigning()){
                        $res = true;
                    }
                }
            }
            else if($activity->getOrganization() !== null && $activity->getOrganization()->isMember($user)){ // if assign into org
                $res = true;
            }
        }else if(!$activity->getIsPublic() && $user === null){
            $res = false;
        }else { $res = true;}
    return $res;
    }

    private function isActivityFile($activity): bool
    {
        return get_class($activity) === ActivityFile::class;
    }

    private function putFollower(UserInterface $user, Activity $activity){
        if($activity->isFollowByUserId($user->getId())){
            $activity->removeFollower($user);
            $activity->setIsFollowed(false);
        }else{
            $activity->addFollower($user);
            $activity->setIsFollowed(true);
        }
    }
/*
    public function putOnOrganization(UserInterface $user, Organization $org, Activity $activity): Activity
    {
        if($activity->getCreator()->getId() === $user->getId()){
            $activity->setOrganization($org);
        }else {
            throw new UnauthorizedHttpException();
        }
        $this->entityManager->flush();
        return $activity;
    }

    public function putOnProject(UserInterface $user,Project $project, Activity $activity): Activity
    {
        if($activity->getCreator()->getId() === $user->getId()){
            $activity->setProject($project);
        }else {
            throw new UnauthorizedHttpException();
        }
        $this->entityManager->flush();
        return $activity;
    }*/
}