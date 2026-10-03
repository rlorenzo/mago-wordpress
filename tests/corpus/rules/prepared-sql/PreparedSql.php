<?php

declare(strict_types=1);

function myplugin_queries(string $title, int $id, array $ids): void
{
    global $wpdb;

    // @mago-expect lint:wordpress/prepared-sql
    $wpdb->query("SELECT * FROM {$wpdb->posts} WHERE post_title = '" . $title . "'");
    // @mago-expect lint:wordpress/prepared-sql
    $wpdb->get_results("SELECT * FROM $wpdb->posts WHERE post_title = '$title'");
    // @mago-expect lint:wordpress/prepared-sql
    $wpdb->get_var(<<<SQL
        SELECT ID FROM {$wpdb->posts} WHERE post_title = '{$title}'
        SQL);
    // @mago-expect lint:wordpress/prepared-sql
    $wpdb->get_row('SELECT * FROM ' . $wpdb->posts . ' WHERE ID = ' . myplugin_id());
    // @mago-expect lint:wordpress/prepared-sql
    \wpdb::prepare('SELECT * FROM t WHERE a = ' . $title, []);

    $wpdb->query($wpdb->prepare("SELECT * FROM {$wpdb->posts} WHERE post_title = %s", $title));
    $wpdb->query("SELECT * FROM {$wpdb->posts} WHERE ID = " . (int) $id);
    $wpdb->query("SELECT * FROM {$wpdb->posts} WHERE ID = " . absint($id));
    $wpdb->query("SELECT * FROM {$wpdb->posts} WHERE post_title = '" . esc_sql($title) . "'");
    $wpdb->get_col(sprintf('SELECT ID FROM %s WHERE ID IN (%s)', $wpdb->posts, implode(',', array_fill(0, count($ids), '%d'))));
    $wpdb->get_results("SELECT * FROM {$wpdb->posts} LIMIT " . 10 * 2);
    Myplugin\wpdb::prepare('SELECT * FROM t WHERE a = ' . $title);
}
