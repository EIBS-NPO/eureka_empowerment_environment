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

    return $dataResponse;
    }

    /**
     * @param array $params
     * @return Project
     * @throws PartialContentException|ViolationException
     */
    public function create (array $params) :Project
    {
        //check params Validations
        $this->validator->isInvalid(
            ["creator", "title", "description"],
            ["startDate", "endDate"],
            Project::class);

        //create project object && set validated fields
        $project = new Project();
        $project = $this->setProject($project, $params);

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
     * @param Project $project
     * @param array $params
     * @return Project
     * @throws ViolationException
     */
    public function update(Project $project, array $params) :Project
    {
        //check params Validations
        $this->validator->isInvalid(
            [],
            ["title", "description", "startDate", "endDate", "organization"],
            Project::class);

            $project = $this->setProject($project, $params);

            $this->entityManager->flush();
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
        $project = $this->fileHandler->uploadPicture(
            $project,
            self::PICTURE_DIR,
            $params["pictureFile"] === "null" ? null : $params["pictureFile"]
        );

        $this->entityManager->flush();

        return $project;
    }


    private function setProject(Project $project, array $attributes) :Project
    {
        foreach( ["creator", "title", "description", "startDate", "endDate", "organization"]
                 as $field ) {
            if (isset($attributes[$field])) {
                $setter = 'set' . ucfirst($field);
                $project->$setter($attributes[$field]);
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