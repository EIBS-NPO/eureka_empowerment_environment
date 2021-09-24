<?php


namespace App\Controller\admin;


use \Exception;
use App\Entity\Project;
use App\Entity\User;
use App\Services\Entity\ProjectHandler;
use App\Services\FileHandler;
use App\Services\LogService;
use App\Services\Request\ParametersValidator;
use App\Services\Request\RequestParameters;
use App\Services\Request\ResponseHandler;
use App\Services\Entity\FollowingHandler;
use App\Services\Security\RequestSecurity;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class UserAdminController
 * @package App\Controller
 * @Route("/admin/project", name="admin")
 */
class ProjectAdminController extends AbstractController
{

    private RequestSecurity $security;
    private RequestParameters $parameters;
    private ResponseHandler $responseHandler;
    private ParametersValidator $validator;
    protected EntityManagerInterface $entityManager;
    protected FileHandler $fileHandler;
    private LogService $logger;
    private FollowingHandler $followingHandler;
    private ProjectHandler $projectHandler;

    /**
     * UserController constructor.
     * @param RequestParameters $requestParameters
     * @param ResponseHandler $responseHandler
     * @param ParametersValidator $validator
     * @param EntityManagerInterface $entityManager
     * @param FileHandler $fileHandler
     * @param LogService $logger
     * @param FollowingHandler $followingHandler
     */
    public function __construct(RequestParameters $requestParameters, ResponseHandler $responseHandler, ParametersValidator $validator, EntityManagerInterface $entityManager, FileHandler $fileHandler, LogService $logger, FollowingHandler $followingHandler, ProjectHandler $projectHandler)
    {
        $this->parameters = $requestParameters;
        $this->responseHandler = $responseHandler;
        $this->validator = $validator;
        $this->entityManager = $entityManager;
        $this->fileHandler = $fileHandler;
        $this->logger = $logger;
        $this->followingHandler = $followingHandler;

        $this->projectHandler = $projectHandler;
    }

    /**
     * returns to a user his created projects
     * @Route("", name="_get", methods="get")
     * @param Request $request
     * @return Response
     */
    public function getProjects(Request $request): Response
    {
        try {
            // recover all data's request
            $this->parameters->setData($request);

            //force admin access
            $this->parameters->putData("access", "admin");

            $projects = $this->projectHandler->getProjects($this->getUser(), $this->parameters->getAllData());

            //download picture
            foreach ($projects as $key => $project) {
                foreach ($project->getActivities() as $activity) {
                    $activity = $this->fileHandler->loadPicture($activity);
                }
                if ($project->getOrganization() !== null) {
                    $project->setOrganization($this->fileHandler->loadPicture($project->getOrganization()));
                }
                $projects[$key] = $this->fileHandler->loadPicture($project);
            }

            //success response
            return $this->responseHandler->successResponse($projects, "read_project");
        } catch (Exception $e) {
            $this->logger->logError($e, $this->getUser(), "error");
            return $this->responseHandler->serverErrorResponse($e, "An error occured");
        }
    }
}