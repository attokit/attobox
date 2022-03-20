<?php

/**
 * Attobox Framework / Module Doc
 * Doc Parser
 * 
 * parse *.md to html
 */

namespace Atto\Box\doc\parser;

use Atto\Box\doc\Parser;
use Atto\Box\request\Curl;

class Md extends Parser
{
    /**
     * parse method
     * should be overrided
     * @return String html
     */
    public function parse()
    {

        /**
         * parse markdown by using github api
         * https://api.github.com/markdown
         */

        $api = "https://api.github.com/markdown";
        //$api = "https://api.git.sdut.me/markdown";  //mirror

        $curl = new Curl($api);
        $curl->setOpt([
            "header" => false,
            "returntransfer" => true,
            "httpheader" => [
                "Content-type:: application/json",
                "User-Agent: ".$_SERVER["HTTP_USER_AGENT"]
            ],

            "post" => true,
            "postfields" => a2j([
                "mode" => "markdown",
                "text" => $this->raw
            ]),
            
            "ssl_verifypeer" => false,
        ]);

        $this->html = $curl->exec();
        $curl->close();

        //$this->html = $this->raw;

        return $this->html;

    }
}