<?php

namespace SimpleSAML\Module\uab\Auth\Source;

use SimpleSAML\Configuration;
use SimpleSAML\Error\Exception;


trait tConfig{
    public static function loadConfig(string $authId):array{
        $config = Configuration::getConfig('authsources.php');

        $authConfig = $config->getOptionalArray($authId, null);
        if ($authConfig === null):
            throw new Exception(
                'No authentication source with id ' .
                var_export($authId, true) . ' found.'
            );
        endif;
        return $authConfig;
    }
}