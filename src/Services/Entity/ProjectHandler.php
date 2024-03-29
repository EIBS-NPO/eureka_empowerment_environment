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
            || !preg_match('(^assigned$|^followed$|^owned$|^search$|^all$)', $params["access"]) // or if bad access param
            || (isset($params["admin"]) && $user->getRoles()[0] !== "ROLE_ADMIN") // or it's an access param admin with a no admin user
        ) {
            $params["access"] = null;
        }

        if(isset($params['id']) && is_numeric($params["id"])){
            $id = $params["id"];
        }

        switch($params["access"]){
            case "assigned":
                if(isset($id)){
                    $dataResponse = $this->projectRepo->findAssignedById($user->getId(), $id);
                } else{
                    $dataResponse = $this->projectRepo->findAssigned($user->getId());
                }
                break;
            case "followed":
                if(isset($id)){
                    $dataResponse = $this->projectRepo->findFollowedById($user->getId(), $id);
                } else{
                    $dataResponse = $this->projectRepo->findFollowed($user->getId());
                }
                break;
            case "owned":
                if(isset($id)){
                    $dataResponse = $this->projectRepo->findBy(['creator'=>$user, "id"=> $id ]);
                } else{
                    $dataResponse = $this->projectRepo->findBy(["creator"=>$user]);
                }
                break;
            case "search":
                $dataResponse = $this->projectRepo->search( $this->getSearchCriterias($params) );
                break;
            case "all":
                $dataResponse = $this->projectRepo->findAll();
                break;
            default : //admin or no access param
                if(isset($id)){
                    $dataResponse = $this->projectRepo->findBy(["id"=> $id ]);
                }else {
                    $dataResponse = $this->projectRepo->findAll();
                }
        }

        if($withNotFound && count($dataResponse) === 0){
            if(isset($id)){
                $msg = "project id : $id ";
            }else {
                $msg ="no projects found";
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
     * @throws PartialContentException | ViolationException | FileException
     */
    public function create (UserInterface $user, array $params) :Project
    {
        //check params Validations
        $this->validator->isInvalid(
            ["creator", "title", "description"],
            [],
            Project::class);

        //create project object && set validated fields
        $project = new Project();
        $project->setCreator($user);
        $project->setTitle($params["title"]);
        $project->setDescription($params["description"]);

        if(isset($params["startDate"])) $this->putDate($project, "startDate", $params["startDate"] !== "null" ? $params["startDate"] : null);
        if(isset($params["endDate"])) $this->putDate($project, "endDate", $params["endDate"] !== "null" ? $params["endDate"] : null);

        //Optional image management without blocking the creation of the entity
        try{
            if(isset($params["pictureFile"])){ //can't be null for creating
                $project = $this->fileHandler->uploadPicture($project,self::PICTURE_DIR, $params["pictureFile"]);
            }
        }catch(FileException | BadMediaFileException $e){
            throw new PartialContentException([$project], $e->getMessage());
        } finally {
            //persist
         //   dd($project);
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
                ["title", "description"],
                Project::class
            );

            $project = $this->setProject($user, $project, $params);

            if(isset($params["follow"])){
                $project = $this->followingHandler->putFollower($project, $user);
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
     * @param $pictureFile
     * @return PictorialObject
     * @throws BadMediaFileException
     */
    public function putPicture(Project $project, $pictureFile): PictorialObject
    {
        return $this->fileHandler->uploadPicture(
            $project,
            self::PICTURE_DIR,
            $pictureFile === "null" ? null : $pictureFile
        );
    }


    /**
     * @throws BadMediaFileException
     * @throws ViolationException
     */
    private function setProject(UserInterface $user, Project $project, array $attributes) :Project
    {
        if ($this->followingHandler->isAssign($project, $user) || isset($attributes["admin"])) {
            // project isn't new and user is assigned ? (owner or member) //exclude admin

            foreach (["creator", "title", "description", "startDate", "endDate", "organization", "activity", "member", "pictureFile"]
                     as $field) {
                if (isset($attributes[$field])) {

                    //handle particular attributes
                    if (preg_match('(^organization$|^activity$|^member$|^pictureFile$|^startDate$|^endDate$)', $field)) {
                        //force true null type
                        if ($attributes[$field] === "null") $attributes[$field] = null;

                            //put orgHandle
                            if ($field === "organization") {
                                $org = $attributes[$field];
                                $className = $this->entityManager->getMetadataFactory()->getMetadataFor(get_class($org))->getName();
                                if (!is_string($org) && $className === Organization::class) {
                                    $this->putOrganization($user, $project, $org);
                                } else throw new ViolationException("invalid " . $field . " parameter");
                            }

                            //put activity handle
                            if ($field === "activity") {
                                $activity = $attributes[$field];
                                $className = $this->entityManager->getMetadataFactory()->getMetadataFor(get_class($activity))->getName();
                                if (!is_string($activity)
                                    && ( get_class($activity) === Activity::class || $className === ActivityFile::class) ) {
                                    $this->putActivity($user, $project, $activity);
                                } else throw new ViolationException("invalid " . $field . " parameter");
                            }

                            //handle only for project's creator
                            if ($project->getCreator() === $user || isset($attributes["admin"])) {

                                //handler put member
                                if ($field === "member") {
                                    $member = $attributes[$field];
                                    $className = $this->entityManager->getMetadataFactory()->getMetadataFor(get_class($member))->getName();
                                    if ($className === User::class && $member !== $project->getCreator()) {
                                        $this->followingHandler->putAssigned($project, $member);
                                    }
                                }

                                //handle put picture
                                if ($field === "pictureFile") {
                                    $pictureFile = $attributes[$field];
                                    $project = $this->putPicture($project, $pictureFile);
                                }
                            }

                            //dating
                            if ($field === "startDate" || $field === "endDate") {
                                $project = $this->putDate($project, $field, $attributes[$field]);
                            }
                    } else {
                        $setter = 'set' . ucfirst($field);
                        $project->$setter($attributes[$field]);
                    }
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

    private function putActivity (UserInterface $user, Project $project, Activity $activity){
        if($activity->getProject() === $project){
            if ($activity->getCreator() === $user || $project->getCreator() === $user || $user->getRoles()[0] === "ROLE_ADMIN")
            { //if activity's creator or project's creator
                $project->removeActivity($activity);
            }
        }
        else { //add only for activity's creator
            if($activity->getCreator() === $user || $user->getRoles()[0] === "ROLE_ADMIN"){
                $project->addActivity($activity);
            }
        }
    }

    private function putOrganization(UserInterface $user, Project $project, Organization $org)
    {
        if($project->getCreator() === $user || $org->getReferent() === $user || $user->getRoles()[0] === "ROLE_ADMIN") {
            if ($project->getOrganization() === $org) {
                $project->setOrganization(null);
            } else { // add only for org's referent
                if($org->getReferent() === $user || $user->getRoles()[0] === "ROLE_ADMIN"){
                    $project->setOrganization($org);
                }
            }
        }
    }

    /**
     * @throws ViolationException
     */
    private function putDate(Project $project, String $dateName, ?\DateTimeInterface $date ): Project
    {
        if(is_null($date)){
            $strMethode = "remove".ucfirst($dateName);
            $project->$strMethode();
        }else {
            $this->validator->isInvalid(
                [$dateName],
                [],
                Project::class);
            $strMethode = "set".ucfirst($dateName);
            $project->$strMethode($date);
        }

        return $project;
    }

    private function getSearchCriterias(Array $params): array
    {
        $criterias = [];
        foreach( ["id", "title", "type", "email", "phone", "creator_id", "creator_firstname", "creator_lastname", "creator_email", "organization_id", "organization_name", "organization_email", "followings_isAssigning", "follower_id"] as $field ) {
            if(isset($params[$field])){
                $criterias[$field] = $params[$field];
            }
        }
        return $criterias;
    }
}