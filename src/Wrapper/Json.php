<?php


namespace Ieu\Httpider\Wrapper;


use Flow\JSONPath\JSONPath;

class Json extends JSONPath
{
    public function __construct($data, $options = 0)
    {
        if (is_string($data)) {
            $data = json_decode($data, JSON_THROW_ON_ERROR);
        }
        parent::__construct($data, $options);
    }
}
