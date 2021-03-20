<?php


namespace App\Service\Request;


use App\Exceptions\ViolationException;
use Symfony\Component\HttpFoundation\Request;

class RequestParameters
{
    private array $data = [];

    /**
     * @param $key
     * @return false|mixed
     */
    public function getData($key){
        if(isset($this->data[$key])){
            return $this->data[$key];
        }
        return false;
    }

    public function getAllData(){
        return $this->data;
    }

    public function putData(String $key, $value){
        $this->data[$key] = $value;
    }

    /**
     * @param Request $request
     */
    public function setData(Request $request) :void {
        switch($request->getMethod()){
            case "DELETE":
            case "GET":
                $this->data = array_merge($this->data, $request->query->all());
                break;
            case "PUT":
            case "POST":
                $this->data = array_merge($this->data, $request->request->all());
                $this->data = array_merge($this->data, $request->files->all());
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
    }

    /**
     * @param String $key
     * @param $param
     */
    public function addParam(String $key, $param) :void {
        $this->data[$key] = $param;
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

    /**
     * @param array $dataKeys
     * @return void
     * @throws ViolationException
     */
    public function hasData(array $dataKeys) :void
    {
        $tabMissing = [];
        foreach ($dataKeys as $key) {
            if (!isset($this->data[$key])) {
                $tabMissing[] = "missing parameter : " . $key . " is required. ";
            }
        }
        if (count($tabMissing) > 0) {
            throw new ViolationException($tabMissing);
        }
    }
}