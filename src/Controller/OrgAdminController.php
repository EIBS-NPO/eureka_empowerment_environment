<?php


namespace App\Controller;


use App\Entity\Organization;
use Exception;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class OrgAdminController
 * @package App\Controller
 * @Route("/admin/org", name="org")
 */
class OrgAdminController extends CommonController
{ //todo suppr commonController
    /**
     * @Route("", name="_get", methods="get")
     * @param Request $insecureRequest
     * @return Response
     */
    public function getOrganization(Request $insecureRequest){

        // recover all data's request
        $this->dataRequest = $this->requestParameters->getData($this->request);

        try{
            if(isset($data['id']) && !is_numeric($data['id'])){
                throw new Exception("id parameter must be numeric", Response::HTTP_BAD_REQUEST);
            }
        }catch(\Exception $e){
         //   $this->logger->error($e);
            return new Response(
                json_encode(["error" => $e->getMessage()]),
                $e->getCode(),
                ["Content-Type" => "application/json"]
            );
        }

      //  $this->getEntities(Organization::class,"id");
        $orgRepository = $this->entityManager->getRepository(Organization::class);
        try{
            //query for organizationData
            if(count($data) > 0 && (isset($data['id']))){
                $orgData = $orgRepository->findBy(['id' => $data['id']]);
            }else { //otherwise we return all users
                $orgData = $orgRepository->findAll();
            }
        }
        catch(\Exception $e){
        //    $this->logger->error($e);
            return new Response(
                json_encode(["error" => "ERROR_SERVER"]),
                $e->getCode(),
                ["Content-Type" => "application/json"]
            );
        }

        //serialize all found data
        if (count($orgData) > 0 ) {
            foreach($orgData as $key => $org){
                $orgData[$key] = $org->serialize("read_organization");
            }
        }

        //success
        return new Response(
            json_encode(["success" => true, "data" => $orgData]),
            Response::HTTP_OK,
            ["content-type" => "application/json"]
        );
    }

    //todo update org for change a referent ?
}