<?php

/**
 * 通过 Response::page() 输出的页面里可用的 functions
 */

return [
    "p" => function(){
        global $_Params;
        var_dump($_Params);
    },
];