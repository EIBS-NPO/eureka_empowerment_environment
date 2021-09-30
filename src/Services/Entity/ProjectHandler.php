<?php

namespace App\Services\Entity;

use App\Entity\Interfaces\PictorialObject;
use App\Entity\Project;
use App\Entity\User;
use App\Exceptions\BadMediaFileException;
use App\Exceptions\NoFoundException;
use App\Exceptions\PartialContentException;
use App\Exceptions\ViolationException;
use App\Repository\ProjectRepository;
use App\Services\FileHandler;
use App\Services\Request\ParametersValidator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\Security\Core\User\UserInterface;

/**
* Class ProjectHandler
* @package App\Services\Entity
*/
class ProjectHandler
{
    Const PICTURE_DIR = '/pictures/Project';

    private EntityManagerInterface $entityManager;
    private ProjectRepository $projectRepo;
    private FileHandler $fileHandler;
    private FollowingHandler $followingHandler;
    private ParametersValidator $validator;

    /**
     * @param EntityManagerInterface $entityManager
     * @param FileHandler $fileHandler
     * @param FollowingHandler $followingHandler
     * @param ParametersValidator $validator
     */
    public function __construct(EntityManagerInterface $entityManager, FileHandler $fileHandler, FollowingHandler $followingHandler, ParametersValidator $validator, ProjectRepository $projectRepo)
    {
        $this->entityManager = $entityManager;
        $this->projectRepo = $projectRepo;
        $this->fileHandler = $fileHandler;
        $this->followingHandler = $followingHandler;
        $this->validator = $validator;
    }

    /**
     * @throws NoFoundException
     */
    public function getProjects (?UserInterface $user, $params, bool $withNotFound=false): array
    {

        //check access
        if ($user === null || //if not connected user
            !isset($params["access"]) // or if no access param defined
            || !preg_match('(^assigned$|^followed$|^owned$|^admin$)', $params["access"]) // or if bad access param
            || ($params["access"] === "admin" && $user->getRoles()[0] !== "ROLE_ADMIN") // or it's an access param admin with a no admin user
        ) {
            $params["access"] = null;
        }

        switch($params["access"]){
            case "assigned":
                if(isset($params['id'])){
                    $dataResponse = $this->projectRepo->findAssignedById($user->getId(), $params['id']);
                } else{
                    $dataResponse = $this->projectRepo->findAssigned($user->getId());
                }
                break;
            case "followed":
                if(isset($params['id'])){
                    $dataResponse = $this->projectRepo->findFollowedById($user->getId(), $params['id']);
                } else{
                    $dataResponse = $this->projectRepo->findFollowed($user->getId());
                }
                break;
            case "owned":
                if(isset($params['id'])){
                    $dataResponse = $this->projectRepo->findBy(['creator'=>$user, "id"=>$params["id"]]);
                } else{
                    $dataResponse = $this->projectRepo->findBy(["creator"=>$user]);
                }
                break;
            default : //admin or no access param
                if(isset($params['id'])){
                    $dataResponse = $this->projectRepo->findBy(["id"=>$params['id']]);
                }else {
                    $dataResponse = $this->projectRepo->findAll();
                }
        }

        if($withNotFound && count($dataResponse) === 0){
            if(isset($id)){
                $msg = "[project id : ".$id . "]";
            }else {
                $msg ="no project found";
            }
            if($user !== null){
                $msg .= " for [user : ".$user->getId() . "]";
            }
            throw new NoFoundException($msg);
        }

        //if current user isn't assigned or creator, filter private activities in each project result
        if($params["access"] === "followed"){
            foreach ($dataResponse as $project) {
                if (!$this->followingHandler->isAssign($project, $user)) {
                    $project->setActivities($project->getOnlyPublicActivities());
                }
            }
        }

        //only with public resources for public access
        if($params["access"] === null ){
            foreach($dataResponse as $project) {
                $project->setActivities($project->getOnlyPublicActivities());
            }
        }

        //todo perte du status followed si'j'afficher dans un ctx public meme si loged ....
        //check sur la route public si on peut pass le getUser() et c'est bon!
        //for loged user, add if the current user isAssgn and follower in object
        if(!is_null($user)){
            foreach($dataResponse as $project){
                $project->setIsAssigned($this->followingHandler->isAssign($project, $user));
                $project->setIsFollowed($this->followingHandler->isFollowed($project, $user));
            }
        }

    return $dataResponse;
    }

    /**
     * @param UserInterface $user
     * @param array $params
     * @return Project
     * @throws PartialContentException
     * @throws ViolationException
     */
    public function create (UserInterface $user, array $params) :Project
    {
        //check params Validations
        $this->validator->isInvalid(
            ["creator", "title", "description"],
            ["startDate", "endDate"],
            Project::class);

        //create project object && set validated fields
        $project = new Project();
        $project = $this->setProject($user, $project, $params);

        //Optional image management without blocking the creation of the entity
        try{
            if(isset($params["pictureFile"])){ //can't be null for creating
                $project = $this->fileHandler->uploadPicture($project,self::PICTURE_DIR, $params["pictureFile"]);
            }
        }catch(FileException | BadMediaFileException $e){
            throw new PartialContentException([$project], $e->getMessage());
        } finally {
            //persist
            $this->entityManager->persist($project);
            $this->entityManager->flush();
        }

    return $project;
    }

    /**
     * @param UserInterface $user
     * @param Project $project
     * @param array $params
     * @return Project
     * @throws PartialContentException
     * @throws ViolationException
     */
    public function update(UserInterface $user, Project $project, array $params) :Project
    {
        try{
            //check params Validations
            $this->validator->isInvalid(
                [],
                ["title", "description", "startDate", "endDate"],
                Project::class
            );

            $project = $this->setProject($user, $project, $params);

            //handle optionnal picture
            if(isset($params["pictureFile"])){ //can't be null for creating
                $project = $this->putPicture($project, $params);
            }

            if(isset($params["follow"])){
                $project = $this->followingHandler->putFollower($project, $user);
            }
            if(isset($params["assign"])){
                $project = $this->followingHandler->putAssigned($project, $user);
            }

        }catch(FileException | BadMediaFileException $e){
            throw new PartialContentException([$project], $e->getMessage());
        }finally {
            $this->entityManager->flush();
        }

    return $project;
    }


    /**
     * if PictureFile is null, delete the oldPicture, else save the new.
     * @param Project $project
     * @param $params
     * @return PictorialObject
     * @throws BadMediaFileException
     */
    public function putPicture(Project $project, $params): PictorialObject
    {
        return $this->fileHandler->uploadPicture(
            $project,
            self::PICTURE_DIR,
            $params["pictureFile"] === "null" ? null : $params["pictureFile"]
        );
    }


    private function setProject(UserInterface $user, Project $project, array $attributes) :Project
    {
       // dd($project->getActivities());
        foreach( ["creator", "title", "description", "startDate", "endDate", "organization", "activity"]
                 as $field ) {
            if (isset($attributes[$field])) {
                $canSet = false;
                $setter = 'set' . ucfirst($field);

                //todo check access creator and assign
                if(($field === "organization" || $field === "activity")){
                    if(!is_null($project->getId()) && $project->getCreator()->getId() === $user->getId() ) {//only if project isn't a new Object and currentUser is owner
                        if($attributes[$field] === "null"){ $attributes[$field] = null;}

                        if($field === "organization") {
                            $org = $attributes[$field];
                            if (is_null($org) || $org->isMember($user)) {
                                $canSet = true;
                            }
                        }

                        if($field === "activity") {
                            $activity = $attributes[$field];

                    //if activity have the project, remove it else add
                            if(!is_null($activity)){
                                 if($activity->getProject() === $project){
                                     $project->removeActivity($activity);
                                 }
                                 else { //add
                                     $project->addActivity($activity);
                                 }
                            }

                        }
                    }
                }else{ $canSet = true; }

                if( $canSet ){
                    $project->$setter($attributes[$field]);
                }
            }
        }
        return $project;
    }

    /**
     * @param array $projects
     * @return array
     */
    public function withPictures(array $projects): array
    {
        foreach ($projects as $key => $project) {
            foreach ($project->getActivities() as $activity) {
                $activity = $this->fileHandler->loadPicture($activity);
            }
            if ($project->getOrganization() !== null) {
                $project->setOrganization($this->fileHandler->loadPicture($project->getOrganization()));
            }
            $projects[$key] = $this->fileHandler->loadPicture($project);
        }
        return $projects;
    }

    public function getTeam(Project $project){
        //get collection of assigned user
        return $this->followingHandler->getAssignedTeam($project);
    }
}