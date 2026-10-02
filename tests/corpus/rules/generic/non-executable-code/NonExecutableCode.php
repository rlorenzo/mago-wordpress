<?php

declare(strict_types=1);

function non_executable_after_return(int $a): int
{
    return $a;
    // each_unreachable_line_is_flagged_once
    // @mago-expect lint:generic/non-executable-code
    echo 'one'; echo 'two';
    // @mago-expect lint:generic/non-executable-code
    echo 'three'
        // @mago-expect lint:generic/non-executable-code
        . 'four';
}

function non_executable_nested(array $list): void
{
    foreach ($list as $item) {
        if ($item) {
            continue;
            // @mago-expect lint:generic/non-executable-code
            echo $item;
        }

        echo 'reachable after the if block';
    }

    // a_nested_function_body_is_skipped
    throw new \RuntimeException();
    // @mago-expect lint:generic/non-executable-code
    $f = function (): void {
        echo 'skipped';
    };
}

function non_executable_expressions(?string $a): string
{
    // throw_and_exit_inside_expressions_do_not_count
    $b = $a ?? throw new \RuntimeException();
    $a || exit(1);

    // unbraced_if_bodies_do_not_count
    if ($b === '') return 'x';

    return $b;
}

function non_executable_bare_return(): void
{
    echo 'x';
    // @mago-expect lint:generic/non-executable-code
    return;
}

function non_executable_switch(int $a): void
{
    switch ($a) {
        case 1:
            break;
            // @mago-expect lint:generic/non-executable-code
            echo 'after break';
        default:
            echo 'reachable';
    }
}

function non_executable_ignored(): int
{
    return 1;
    // phpcs:ignore Squiz.PHP.NonExecutableCode.Unreachable
    echo 'x';
}
