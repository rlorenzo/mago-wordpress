<?php

declare(strict_types=1);

// unrelated_prefixed_call_is_allowed
my_mysql_info();
wrap_mysql_info();
prefix_mysql_info();

// near_miss_is_allowed
// mysqlnd_ms_* requires the trailing underscore; this name is missing it.
mysqlnd_msinfo();

// method_definition_is_allowed
class Foo
{
    public function mysql_info(): void {}

    public static function alsoMysqlInfo(): void {}
}

// method_call_is_allowed
$foo = new Foo();
$foo->mysql_info();
Foo::alsoMysqlInfo();

// mysql_extension_is_flagged
// @mago-expect lint:wordpress/db-restricted-functions
mysql_affected_rows();
// @mago-expect lint:wordpress/db-restricted-functions
Mysql_CONNECT();
// @mago-expect lint:wordpress/db-restricted-functions
\MYSQL_close();
// @mago-expect lint:wordpress/db-restricted-functions
mysql_query();

// mysqli_extension_is_flagged
// @mago-expect lint:wordpress/db-restricted-functions
mysqli_connect();
// @mago-expect lint:wordpress/db-restricted-functions
mysqli_real_connect();

// mysqlnd_ms_extension_is_flagged
// @mago-expect lint:wordpress/db-restricted-functions
mysqlnd_ms_get_stats();

// mysqlnd_qc_extension_is_flagged
// @mago-expect lint:wordpress/db-restricted-functions
mysqlnd_qc_clear_cache();

// mysqlnd_uh_extension_is_flagged
// @mago-expect lint:wordpress/db-restricted-functions
mysqlnd_uh_convert_to_mysqlnd();

// mysqlnd_memcache_extension_is_flagged
// @mago-expect lint:wordpress/db-restricted-functions
mysqlnd_memcache_set();

// maxdb_extension_is_flagged
// @mago-expect lint:wordpress/db-restricted-functions
maxdb_connect();

// wp_native_functions_are_allowed
mysql_to_rfc3339();
Mysql_to_RFC3339();
mysql2date();
wp_check_mysql_version();
WP_Date_Query::build_mysql_datetime();

// fully_qualified_global_call_is_flagged
// @mago-expect lint:wordpress/db-restricted-functions
\mysql_connect();

// namespaced_calls_are_allowed
MyNamespace\mysqli_init();
\MyNamespace\mysqlnd_qc_clear_cache();
namespace\Sub\mysqli_fetch();
