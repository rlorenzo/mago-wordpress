<?php

declare(strict_types=1);

namespace {
    // extract_call_is_flagged
    // @mago-expect lint:wordpress/dont-extract
    extract($args);

    // fully_qualified_extract_is_flagged
    // @mago-expect lint:wordpress/dont-extract
    \extract($args);

    // extract_is_case_insensitive
    // @mago-expect lint:wordpress/dont-extract
    EXTRACT($args);

    // extract_inside_function_is_flagged
    function myplugin_render($args)
    {
        // @mago-expect lint:wordpress/dont-extract
        extract($args, EXTR_SKIP);
    }

    // method_call_named_extract_is_not_flagged
    $parser->extract($data);

    // static_call_named_extract_is_not_flagged
    Parser::extract($data);

    // similarly_named_function_is_not_flagged
    extract_data($args);
    my_extract($args);

    // qualified_namespaced_extract_is_not_flagged
    Util\extract($args);
}

namespace App {
    // extract_inside_namespace_is_flagged
    // @mago-expect lint:wordpress/dont-extract
    extract($args);
}

namespace AppImported {
    use function Util\extract;

    // imported_namespaced_extract_is_not_flagged
    extract($args);
}
