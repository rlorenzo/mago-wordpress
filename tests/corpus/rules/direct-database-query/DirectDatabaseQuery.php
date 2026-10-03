<?php

declare(strict_types=1);

function myplugin_uncached(int $id): mixed
{
    global $wpdb;

    // @mago-expect lint:wordpress/direct-database-query
    // @mago-expect lint:wordpress/direct-database-query
    return $wpdb->get_var($wpdb->prepare("SELECT post_title FROM {$wpdb->posts} WHERE ID = %d", $id));
}

function myplugin_cached(int $id): mixed
{
    global $wpdb;

    $title = wp_cache_get("title-{$id}", 'myplugin');
    if ($title === false) {
        // @mago-expect lint:wordpress/direct-database-query
        $title = $wpdb->get_var($wpdb->prepare("SELECT post_title FROM {$wpdb->posts} WHERE ID = %d", $id));
        wp_cache_set("title-{$id}", $title, 'myplugin');
    }

    return $title;
}

function myplugin_write(int $id): void
{
    global $wpdb;

    // @mago-expect lint:wordpress/direct-database-query
    $wpdb->update($wpdb->posts, ['post_status' => 'draft'], ['ID' => $id]);
    clean_post_cache($id);

    // @mago-expect lint:wordpress/direct-database-query
    $wpdb->insert($wpdb->postmeta, ['post_id' => $id, 'meta_key' => 'k']);
}

function myplugin_schema(): void
{
    global $wpdb;

    // @mago-expect lint:wordpress/direct-database-query
    // @mago-expect lint:wordpress/direct-database-query
    // @mago-expect lint:wordpress/direct-database-query
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}myplugin");
    $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}myplugin");
    $wpdb->prefix;
}
