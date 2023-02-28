<?php

declare(strict_types=1);

namespace SimpleSAML\Module\uab;

use SimpleSAML\Configuration;
use SimpleSAML\XHTML\Template as bTemplate;

/**
 * Common code for building SAML 2 messages based on the available metadata.
 *
 * @package SimpleSAMLphp
 */
class Template extends bTemplate {
    /**
     * Constructor
     *
     * @param \SimpleSAML\Configuration $configuration Configuration object
     * @param string                   $template Which template file to load
     */
    public function __construct(Configuration $configuration, string $template){
        parent::__construct($configuration, $template);

        $this->data['links'] = array_merge_recursive($configuration->getOptionalArray('uab:loginpage_links', []));
    }
}
