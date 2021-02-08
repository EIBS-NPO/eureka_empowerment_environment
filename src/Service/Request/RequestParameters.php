<?php


namespace App\Service\Request;


use Symfony\Component\HttpFoundation\Request;

class RequestParameters
{
    private array $data = [];

    public function getData(Request $request){

        switch($request->getMethod()){
            case "GET":
                $this->data = array_merge($this->data, $request->query->all());
                break;
            case "POST":
                $this->data = array_merge($this->data, $request->request->all());
                break;
        }

        //extract body data
        switch($request->getContentType()){
            case "form":
                    $this->extractFormContent($request->getContent());
                break;
            case "json":
                if($this->isJSON($request->getContent())){
                    $this->data = array_merge($this->data, json_decode($request->getContent(),JSON_OBJECT_AS_ARRAY));
                }
                break;
        }

        return $this->data;
    }

    /**
     * @param $string
     * @return bool
     */
    private function isJSON($string) : bool {
        return is_string($string)
            && is_array(json_decode($string, true))
            && (json_last_error() == JSON_ERROR_NONE);
    }

    /**
     * @param $content
     */
    private function extractFormContent($content) : void {
        foreach(explode("&", $content) as $value){
            $value = urldecode($value);
            $line = explode("=", $value);
            $this->data = array_merge($this->data, [$line[0] => $line[1]]);
        }
    }
}