<?php


namespace App\Service\Request;

use Exception;
use Symfony\Component\HttpFoundation\Response;

class ResponseHandler
{
    private array $dataResponse;
    private Response $response;

    /**
     * @return array
     */
    public function getDataResponse(): array
    {
        return $this->dataResponse;
    }

    /**
     * @param array $dataResponse
     */
    public function setDataResponse(array $dataResponse): void
    {
        $this->dataResponse = $dataResponse;
    }


    /**
     * @param $datas
     * @param String|null $context
     * @return mixed
     */
    public function serialize($datas, String $context = null){
        foreach($datas as $key => $data){
            if(gettype( $data) !== "string" && gettype( $data) !== "boolean" && gettype( $data) !== "array"){
                $datas[$key] =  $data->serialize($context);
            }
        }
        return $datas;
    }

    /**
     * @param array $data
     * @param String|null $context
     * @return Response
     */
    public function successResponse(array $data, String $context = null) : Response {
        return $this->response =  new Response(
            json_encode( $this->serialize($data, $context) ),
            Response::HTTP_OK,
            ["content-type" => "application/json"]
        );
    }

    public function BadRequestResponse(Array $violations) :Response{
        return  $this->response =  new Response(
            json_encode($violations),
            Response::HTTP_BAD_REQUEST,
            ["content-type" => "application/json"]
        );
    }

    //todo itilité?
    /**
     * @return Response
     */
    public function notFoundResponse() :Response{
        return  $this->response =  new Response(
        //todo stocker/ construire la chaine message log dans le service log
        //$logInfo .= " | DATA_NOT_FOUND";
            json_encode(["DATA_NOT_FOUND"]),
            Response::HTTP_OK,
            ["content-type" => "application/json"]
        );
    }

    public function BadMediaResponse($message) :Response{
        return  $this->response =  new Response(
            json_encode($message),
            Response::HTTP_UNSUPPORTED_MEDIA_TYPE,
            ["content-type" => "application/json"]
        );
    }






    public function CorruptResponse(String $message) :Response{
        return  $this->response =  new Response(
            json_encode($message),
            Response::HTTP_UNAUTHORIZED,
            ["content-type" => "application/json"]
        );
    }


    /**
     * @param Exception $e
     * @param String $logInfo
     * @return Response
     */
    public function serverErrorResponse(Exception $e, String $logInfo) :Response
    {
        //todo check message
        return $this->response = new Response(
            json_encode($e->getMessage()),
            Response::HTTP_INTERNAL_SERVER_ERROR,
            ["Content-Type" => "application/json"]
        );
    }


    /**
     * @param $message
     * @return Response
     */
    public function unauthorizedResponse($message){
        return $this->response = new Response(
            json_encode($message),
            Response::HTTP_UNAUTHORIZED,
            ["Content-Type" => "application/json"]
        );
    }

    /**
     * @return Response
     */
    public function forbidden() : Response{
       return $this->response = new Response(
            json_encode(["error" => "ACCESS_FORBIDDEN"]),
            Response::HTTP_FORBIDDEN,
            ["Content-Type" => "application/json"]);
    }
}