<?php

/**
 * Attobox Framework / Module Doc
 * Doc Parser
 * 
 * parse doc from [ds, xml, ...] to html
 */

namespace Atto\Box\doc;

class Parser
{
    //original doc content
    public $raw = "";

    //parsed html content
    public $html = "";

    /**
     * construct
     */
    public function __construct($content = "")
    {
        $this->raw = $content;
    }

    /**
     * parse method
     * should be overrided
     * @return String html
     */
    public function parse()
    {

        //...

        return $this->html;
    }
    
}