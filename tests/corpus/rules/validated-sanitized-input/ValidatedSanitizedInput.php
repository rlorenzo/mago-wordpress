<?php

declare(strict_types=1);

function myplugin_read_input(): void
{
    // Not validated, not unslashed, not sanitized.
    // @mago-expect lint:wordpress/validated-sanitized-input(3)
    $a = $_POST['a'];

    // Validated and sanitized, but not unslashed.
    if (isset($_GET['b'])) {
        // @mago-expect lint:wordpress/validated-sanitized-input
        $b = sanitize_text_field($_GET['b']);
    }

    // Validated, unslashed and sanitized.
    if (isset($_GET['c'])) {
        $c = sanitize_text_field(wp_unslash($_GET['c']));
    }

    // `??` validates; an unslashing sanitizer and a safe cast need no wp_unslash().
    $d = absint($_REQUEST['d'] ?? 0);
    $e = isset($_COOKIE['e']) ? (int) $_COOKIE['e'] : 0;

    // A comparison needs no sanitizing.
    if (isset($_POST['f']) && 'yes' === $_POST['f']) {
        $f = true;
    }

    // array_map() with a sanitizing callback.
    if (!empty($_POST['ids'])) {
        $ids = array_map('absint', $_POST['ids']);
    }

    // WordPress does not slash $_SESSION, so it needs no wp_unslash().
    if (isset($_SESSION['g'])) {
        // @mago-expect lint:wordpress/validated-sanitized-input
        $g = $_SESSION['g'];
    }

    // A superglobal interpolated into a string.
    // @mago-expect lint:wordpress/validated-sanitized-input
    echo "Hello {$_GET['name']}";

    // Writing and unsetting are fine, as is reading the whole array.
    $_POST['h'] = 1;
    unset($_POST['h']);
    $all = $_POST;
}
