<?php

declare(strict_types=1);

function myplugin_patterns(string $word): array
{
    return [
        // @mago-expect lint:wordpress/preg-quote-delimiter
        preg_quote($word),
        // @mago-expect lint:wordpress/preg-quote-delimiter
        \PREG_QUOTE(str: $word),
        preg_quote($word, '/'),
        preg_quote(delimiter: '#', str: $word),
        Myplugin\preg_quote($word),
    ];
}
