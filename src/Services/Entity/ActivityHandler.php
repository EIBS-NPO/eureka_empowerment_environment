<?php

namespace App\Services\Entity;

use App\Entity\Activity;
use App\Entity\ActivityFile;
use App\Entity\Interfaces\PictorialObject;
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
        if($params["access"] === "followed"){
            $tab=[];
            foreach ($dataResponse as $activity) {
                if ($this->hasAccess($activity, $user)) {
                    $tab[] = $activity;
                }
            }
            $dataResponse = $tab;
        }

        //only return public resources for public access
        if($params["access"] === null ){
            $tab=[];
            foreach($dataResponse as $activity) {
                if($activity->getIsPublic()){
                    $tab[] = $activity;
                }
            }
            $dataResponse = $tab;
        }

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

        return $dataResponse;
    }


    /**
     * Persist Activity with optional file & picture
     * @param array $params
     * @return Activity activity with no load picture
     * @throws PartialContentException
     * @throws ViolationException
     */
    public function create(array $params) :Activity
    {
        //check params Validations
         $this->validator->isInvalid(
            ["title", "summary", "postDate", "creator", "isPublic"],
            [],
            Activity::class);


        //create Activity object && set validated fields
        $activity = $this->setActivity(new Activity(), $params);
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
     * @throws ViolationException
     */
    public function update($activity, $params) :Activity
    {
        //check params Validations
        $this->validator->isInvalid(
            [],
            ["title", "summary", "isPublic"],
            Activity::class);

        $activity = $this->setActivity($activity, $params);

        $this->entityManager->flush();
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
        $activity = $this->fileHandler->uploadPicture(
            $activity,
            self::PICTURE_DIR,
            $params["pictureFile"] === "null" ? null : $params["pictureFile"]
        );

        $this->entityManager->flush();

        return $activity;
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

        $this->entityManager->flush();
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

      //      $path = '/files/Activity/'.$activityFile->getUniqId(). '_'. $activityFile->getFilename();
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

    private function setActivity(Activity $activity, array $attributes): Activity {
        foreach( ["title", "summary", "postDate", "isPublic"] as $field ) {
            if (isset($attributes[$field])) {
                $setter = 'set' . ucfirst($field);
                $activity->$setter($attributes[$field]);
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
     * @param UserInterface $user
     * @return bool
     */
    private function hasAccess(Activity $activity, ?UserInterface $user): bool
    {
        //need to handle if user ===null
        $res = false;
        if(!$activity->getIsPublic() && $user !== null){
            if($activity->getCreator()->getId() === $user->getId()){
                $res = true;
            }
            else if($activity->getProject() !== null){
                foreach($activity->getProject()->getFollowings() as $following){
                    if($following->getFollower()->getId() === $user->getId() && $following->getIsAssigning()){
                        $res = true;
                    }
                }
            }
            else if($activity->getOrganization() !== null && $activity->getOrganization()->isMember($user)){
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
}