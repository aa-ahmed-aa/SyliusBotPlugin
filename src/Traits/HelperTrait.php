<?php


namespace Ahmedkhd\SyliusBotPlugin\Traits;

trait HelperTrait
{
    /**
     * @param string $string
     * @return bool
     */
    public function  isJson(string $string) {
        \GuzzleHttp\json_decode($string);
        return json_last_error() === JSON_ERROR_NONE;
    }

    /**
     * Convert array multidimensional array to flat array
     * @param array $array
     * @return array
     */
    function arrayFlatten(array $array) {
        $result = array();
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $result = array_merge($result, $this->arrayFlatten($value));
            } else {
                $result = array_merge($result, array($key => $value));
            }
        }
        return $result;
    }
}
