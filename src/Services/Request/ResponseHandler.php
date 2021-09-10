<?php


namespace App\Services\Request;

use App\Exceptions\PartialContentException;
use Exception;
use Symfony\Component\HttpFoundation\Response;

class ResponseHandler
{
    private array $dataResponse = [];
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
            //todo handle case of array, => do not serialize. (but is content must be)
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

    public function BadRequestResponse(String $violations) :Response{
        return  $this->response =  new Response(
            $violations,
            Response::HTTP_BAD_REQUEST,
            ["content-type" => "application/json"]
        );
    }

    //todo useless?
    /**
     * @return Response
     */
    public function notFoundResponse() :Response{
        return  $this->response =  new Response(
            json_encode("data no found"),
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
     * @param String $publicErrorMessage
     * @return Response
     */
    public function serverErrorResponse(String $publicErrorMessage) :Response
    {
        //todo check message
        return $this->response = new Response(
            json_encode($publicErrorMessage),
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

    public function partialResponse(PartialContentException $exception, String $context = null) :Response {
       // array_unshift($data, $exception->getMessage());
        $data = [$exception->getMessage(), $exception->getData()];
        return $this->response = new Response(
            json_encode( $this->serialize( $data, $context ) ),
            Response::HTTP_PARTIAL_CONTENT,
            ["content-type" => "application/json"]
        );
    }

    public function exceptionResponse($exception) : Response{
        return $this->response = new Response(
            $exception->getMessage(),
            $exception->getCode(),
            ["Content-Type" => "application/json"]
        );
    }
}