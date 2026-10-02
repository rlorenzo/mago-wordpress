<?php

declare(strict_types=1);

function myplugin_save_without_nonce(): void
{
    update_option('myplugin', sanitize_text_field(wp_unslash($_POST['value'] ?? '')));
}

function myplugin_save_with_nonce(): void
{
    check_admin_referer('myplugin_save');
    update_option('myplugin', sanitize_text_field(wp_unslash($_POST['value'] ?? '')));
}

function myplugin_isset_before_nonce(): void
{
    // An isset() may come before the nonce check.
    if (!isset($_POST['myplugin_nonce'])) {
        return;
    }

    if (!wp_verify_nonce(sanitize_key($_POST['myplugin_nonce']), 'myplugin')) {
        return;
    }

    update_option('myplugin', absint($_POST['count'] ?? 0));
}

function myplugin_nonce_elsewhere(): void
{
    // A nonce check in the global scope or another function does not count.
    // `$_GET` and `$_REQUEST` are WPCS's `Recommended` (a warning there).
    // @mago-expect lint:wordpress/nonce-verification-warning
    $page = absint($_GET['paged'] ?? 1) + 1;
}

class Myplugin_Form
{
    // A property named like a superglobal is not one.
    public array $_POST = [];

    public function handle(): void
    {
        check_ajax_referer('myplugin');
        $id = absint($_REQUEST['id'] ?? 0);
    }
}
