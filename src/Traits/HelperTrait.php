<?php

declare(strict_types=1);

namespace SyliusBotPlugin\Traits;

use Exception;

trait HelperTrait
{
    /**
     * @param string $content
     * @return bool
     */
    public function  isJson(string $content) {
        json_decode($content);
        return json_last_error() === JSON_ERROR_NONE;
    }

    /**
     * Convert array multidimensional array to flat array
     * @param array $array
     * @return array
     */
    function arrayFlatten(array $array) {
        $result = array();
        /**
         * @psalm-suppress RedundantCondition
         * @var integer $key
         * @var array|string $value
         */
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $result = array_merge($result, $this->arrayFlatten($value));
            } else {
                $result = array_merge($result, [$key => $value]);
            }
        }
        return $result;
    }

    /**
     * @param string $key
     * @return string
     * @throws Exception
     */
    public function getEnvironment(string $key)
    {
        $envs = array_merge($_ENV, getenv());
        /** @var string|null|false $environmentKey */
        $environmentKey = $envs[$key];
        if ($environmentKey === null || $environmentKey === false) {
            throw new Exception("Bad env {$key}");
        }

        return $environmentKey;
    }
}
