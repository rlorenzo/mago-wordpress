<?php

function foo( &...$spread ) {
    bar( ...$spread );

    bar(
        [ ...$foo ],
        ...array_values($keyedArray)
    );
}

// @mago-expect lint:generic/spread-operator-spacing-after
function bar( &   ... $spread ) {
    bar(...


        $spread
    );

    // @mago-expect lint:generic/spread-operator-spacing-after
    bar(
        [...  $foo ],.../*comment*/array_values($keyedArray)
    );
}

