<?php

declare(strict_types=1);

// custom_class_named_like_extension_is_allowed
class Mymysqli
{
}

$ok1 = new Mymysqli();
echo Mymysqli::class;

// class_name_before_double_colon_is_not_confused_with_a_function_call
class NotOurTarget extends ArrayObject
{
    public function doSomething(): void
    {
        echo mysqli();
        if (self::class === static::class) {
        }
    }
}

// new_mysqli_is_flagged
// @mago-expect lint:wordpress/db-restricted-classes
$db1 = new mysqli();
// @mago-expect lint:wordpress/db-restricted-classes
$db2 = new MYSQLI();
// @mago-expect lint:wordpress/db-restricted-classes
$db3 = new \mysqli();

// static_access_is_flagged
// @mago-expect lint:wordpress/db-restricted-classes
echo mysqli::$affected_rows;
// @mago-expect lint:wordpress/db-restricted-classes
mysqli::init();
// @mago-expect lint:wordpress/db-restricted-classes
\mysqli::use_result();
// @mago-expect lint:wordpress/db-restricted-classes
echo PDO::PARAM_INT;

// extends_is_flagged
// @mago-expect lint:wordpress/db-restricted-classes
class MyMysqli extends mysqli
{
}

// @mago-expect lint:wordpress/db-restricted-classes
class YourMysqli extends \mysqli
{
}

// implements_is_flagged
// @mago-expect lint:wordpress/db-restricted-classes
class OurMysqli implements mysqli
{
}

// @mago-expect lint:wordpress/db-restricted-classes
class TheirMysqli implements \MYSQLI
{
}

// extends_and_implements_reports_only_the_restricted_base
// @mago-expect lint:wordpress/db-restricted-classes
class BothMysqli extends mysqli implements ArrayAccess
{
}

// pdo_is_flagged
// @mago-expect lint:wordpress/db-restricted-classes
$db4 = new PDO('sqlite::memory:');
// @mago-expect lint:wordpress/db-restricted-classes
PDO::getAvailableDrivers();
// @mago-expect lint:wordpress/db-restricted-classes
$db5 = (new PDO('sqlite::memory:'))->exec('SELECT 1');

// pdo_statement_is_flagged
// @mago-expect lint:wordpress/db-restricted-classes
$db6 = new PDOStatement();
// @mago-expect lint:wordpress/db-restricted-classes
$db7 = new \PDOStatement();

// anonymous_class_extends_is_flagged
// @mago-expect lint:wordpress/db-restricted-classes
$anon1 = new class extends PDOStatement {
};

// anonymous_class_without_extends_is_allowed
$anon2 = new class {
    public function PDO(): void
    {
    }
};

// enum_implements_is_flagged
// @mago-expect lint:wordpress/db-restricted-classes
enum MysqliEnum implements mysqli
{
}

// new_with_hierarchy_keyword_is_allowed
class Base
{
    public static function make(): static
    {
        return new static();
    }
}

// namespace_keyword_is_case_insensitive
// @mago-expect lint:wordpress/db-restricted-classes
class YourMysqliCaseInsensitive extends NameSpace\mysqli
{
}

// namespaced_class_reference_is_allowed
$obj1 = new MyNamespace\PDO();
$obj2 = new \MyNamespace\PDOStatement();

class MyClass1 extends MyNamespace\mysqli
{
}

class MyClass2 implements \MyNamespace\PDO
{
}

// relative_namespace_keyword_resolves_to_the_current_namespace
// @mago-expect lint:wordpress/db-restricted-classes
$obj3 = new namespace\PDO();

// @mago-expect lint:wordpress/db-restricted-classes
class MyClass4 extends namespace\mysqli
{
}

// @mago-expect lint:wordpress/db-restricted-classes
namespace\MYSQLI::do_something();
