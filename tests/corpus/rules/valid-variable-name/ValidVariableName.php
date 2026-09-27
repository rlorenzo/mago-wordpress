<?php
// @mago-expect lint:wordpress/valid-variable-name
$varName  = 'hello'; // Bad.
$var_name = 'hello';
$varname  = 'hello';
// @mago-expect lint:wordpress/valid-variable-name
$_varName = 'hello'; // Bad.

class MyClass {
	// @mago-expect lint:wordpress/valid-variable-name
	var $varName  = 'hello'; // Bad.
	var $var_name = 'hello';
	var $varname  = 'hello';
	// @mago-expect lint:wordpress/valid-variable-name
	var $_varName = 'hello'; // Bad.

	// @mago-expect lint:wordpress/valid-variable-name
	public $varNamf  = 'hello'; // Bad.
	public bool $var_namf = true;
	public $varnamf  = 'hello';
	// @mago-expect lint:wordpress/valid-variable-name
	public $_varNamf = 'hello'; // Bad.

	// @mago-expect lint:wordpress/valid-variable-name
	protected $varNamg  = 'hello'; // Bad.
	protected $var_namg = 'hello';
	protected $varnamg  = 'hello';
	// @mago-expect lint:wordpress/valid-variable-name
	protected string $_varNamg = 'hello'; // Bad.

	// @mago-expect lint:wordpress/valid-variable-name
	private $_varNamh  = 'hello'; // Bad.
	private $_var_namh = 'hello';
	private $_varnamh  = 'hello';
	// @mago-expect lint:wordpress/valid-variable-name
	private int|string $varNamh   = 'hello'; // Bad.
}

// @mago-expect lint:wordpress/valid-variable-name
echo $varName; // Bad.
echo $var_name;
echo $varname;
// @mago-expect lint:wordpress/valid-variable-name
echo $_varName; // Bad.

// @mago-expect lint:wordpress/valid-variable-name
echo "Hello $varName"; // Bad.
echo "Hello $var_name";
echo "Hello ${var_name}";
echo "Hello $varname";
// @mago-expect lint:wordpress/valid-variable-name
echo "Hello $_varName"; // Bad.

// @mago-expect lint:wordpress/valid-variable-name
echo 'Hello '.$varName; // Bad.
echo 'Hello '.$var_name;
echo 'Hello '.$varname;
// @mago-expect lint:wordpress/valid-variable-name
echo 'Hello '.$_varName; // Bad.

echo $_SERVER['var_name'];
echo $_REQUEST['var_name'];
echo $_GET['var_name'];
echo $_POST['var_name'];
echo $GLOBALS['var_name'];

// @mago-expect lint:wordpress/valid-variable-name
echo MyClass::$varName; // Bad.
echo MyClass::$var_name;
echo MyClass::$varname;
// @mago-expect lint:wordpress/valid-variable-name
echo MyClass::$_varName; // Bad.
// @mago-expect lint:wordpress/valid-variable-name
echo MyClass::$VAR_name; // Bad.

// @mago-expect lint:wordpress/valid-variable-name
echo $this->varName2; // Bad.
echo $this->var_name2;
echo $this->varname2;
// @mago-expect lint:wordpress/valid-variable-name
echo $this->_varName2; // Bad.
// @mago-expect lint:wordpress/valid-variable-name
echo $object->varName2; // Bad.
echo $object->var_name2;
echo $object_name->varname2;
// @mago-expect lint:wordpress/valid-variable-name
echo $object_name->_varName2; // Bad.
// @mago-expect lint:wordpress/valid-variable-name
echo $object_name->VAR_name; // Bad.

echo $this->myFunction($one, $two);
echo $object->myFunction($one_two);

// @mago-expect lint:wordpress/valid-variable-name
$error = "format is \$GLOBALS['$varName']"; // Bad.

echo $_SESSION['var_name'];
echo $_FILES['var_name'];
echo $_ENV['var_name'];
echo $_COOKIE['var_name'];

// @mago-expect lint:wordpress/valid-variable-name
$XML       = 'hello'; // Bad.
// @mago-expect lint:wordpress/valid-variable-name
$myXML     = 'hello'; // Bad.
// @mago-expect lint:wordpress/valid-variable-name
$XMLParser = 'hello'; // Bad.
// @mago-expect lint:wordpress/valid-variable-name
$xmlParser = 'hello'; // Bad.

// @mago-expect lint:wordpress/valid-variable-name
$ID = 1; // Bad.
$post = get_post( $x );
echo $post->ID;

// @mago-expect lint:wordpress/valid-variable-name
echo $comment_ID; // Bad.
// @mago-expect lint:wordpress/valid-variable-name
echo $comment_post_ID; // Bad.
// @mago-expect lint:wordpress/valid-variable-name
echo $comment_author_IP; // Bad.

$comment = get_comment( 1 );
echo $comment->comment_ID;
echo $comment->comment_post_ID;
echo $comment->comment_author_IP;

class Foo {
	public $_public_leading_underscore;
	private $private_no_underscore_loading;

	// @mago-expect lint:wordpress/valid-variable-name
	function Bar( $VARname ) { // Bad.
		// @mago-expect lint:wordpress/valid-variable-name
		$localVariable = false; // Bad.
		// @mago-expect lint:wordpress/valid-variable-name
		echo Some_Class::$VarName; // Bad.
		// @mago-expect lint:wordpress/valid-variable-name
		echo $this->VAR_name; // Bad.
		// @mago-expect lint:wordpress/valid-variable-name
		$_localVariable = false; // Bad.
		// @mago-expect lint:wordpress/valid-variable-name
		echo Some_Class::$_VarName; // Bad.
		// @mago-expect lint:wordpress/valid-variable-name
		echo $this->_VAR_name; // Bad.
	}

	function Baz( $var_name ) { // Ok.
		$local_variable = false; // Ok.
		echo Some_Class::$var_name; // Ok.
		echo $this->var_name; // Ok.
		$_local_variable = false; // Ok.
		echo Some_Class::$_var_name; // Ok.
		echo $this->_var_name; // Ok.
	}
}

if ( is_category() ) {
	$category = get_queried_object();
	$cat_id = $category->cat_ID;
	// @mago-expect lint:wordpress/valid-variable-name
	$cat_ID = $category->cat_ID; // Bad.
}

$EZSQL_ERROR = array(); // OK

// @mago-expect lint:wordpress/valid-variable-name
echo "This is a $comment_ID"; // Bad
echo "This is $PHP_SELF with $HTTP_RAW_POST_DATA"; // Ok.

/*
 * Testing custom properties.
 */
// WPCS allowed_custom_properties has no setting here, so these stay flagged.
// @mago-expect lint:wordpress/valid-variable-name
echo MyClass::$varName;
// @mago-expect lint:wordpress/valid-variable-name
echo $this->DOMProperty;
// @mago-expect lint:wordpress/valid-variable-name
echo $object->varName;

// @mago-expect lint:wordpress/valid-variable-name
echo $object->varName;  // Bad, no longer allowed.

// Code style independent token checking.
echo $object
	// Silly but allowed.
	->
		// Bad.
		// @mago-expect lint:wordpress/valid-variable-name
		varName2
			// More sillyness.
			['test'];
echo $object
	// Silly but allowed.
	->
		// OK.
		var_name2
			// More sillyness.
			['test'];

echo ClassName
	// Silly but allowed.
	::
		// Bad.
		// @mago-expect lint:wordpress/valid-variable-name
		$varName2
			// More sillyness.
			['test'];
echo ClassName
	// Silly but allowed.
	::
		// OK.
		$var_name2
			// More sillyness.
			['test'];

class MultiVarDeclarations {
	// @mago-expect lint:wordpress/valid-variable-name(2)
	public $multiVar1, $multiVar2, // Bad x 2.
		// @mago-expect lint:wordpress/valid-variable-name
		$multiVar3, // Bad.
		// Some comment.
		// @mago-expect lint:wordpress/valid-variable-name
		$multiVar4, // Bad.
		// @mago-expect lint:wordpress/valid-variable-name
		$multiVar5 = false, // Bad.
		// @mago-expect lint:wordpress/valid-variable-name
		$multiVar6 = 123, // Bad.
		$multi_var7 = 'string'; // Ok.

	public function testMultiGlobalAndStatic() {
		// @mago-expect lint:wordpress/valid-variable-name
		global $multiGlobal1, $multi_global2, // Bad x 1.
			// @mago-expect lint:wordpress/valid-variable-name
			$multiGlobal3; // Bad.

		// @mago-expect lint:wordpress/valid-variable-name
		static $multiStatic1, $multi_static2 = false, // Bad x 1.
			// Comment.
			// @mago-expect lint:wordpress/valid-variable-name
			$multiStatic3 = ''; // Bad.
	}
}

// @mago-expect lint:wordpress/valid-variable-name
echo "This is $post_ID with $ThisShouldBeFlagged"; // Bad.


echo "This is \$someName"; // OK, variable is literal text.

// @mago-expect lint:wordpress/valid-variable-name
echo "This is ${$someName}"; // Bad.
// @mago-expect lint:wordpress/valid-variable-name
echo "This is ${Foo}"; // Bad.
echo "This is {${getName()}}"; // OK, expression, should be ignored.
// @mago-expect lint:wordpress/valid-variable-name
echo "This is $Foo?->bar"; // Bad, expression, but the $Foo in it should still be flagged.
echo "This is {$foo['bar']?->baz()()}"; // OK.
// @mago-expect lint:wordpress/valid-variable-name
echo "This is {$Foo['bar']?->baz()()}"; // Bad, expression, but the $Foo in it should still be flagged.

// Safeguard that parameters in all types of function declarations, including PHP 7.4+ arrow functions, are flagged.
function has_params( $without_default, $with_default = 'default' ) {} // OK.
$closure = function ( $without_default, $with_default = 'default' ) {}; // OK.
$arrow = fn ( $without_default, $with_default = 'default' ) => 10; // OK.

// @mago-expect lint:wordpress/valid-variable-name(2)
function has_params_too( $withoutDefault, $withDefault = 'default' ) {} // Bad x 2.
// @mago-expect lint:wordpress/valid-variable-name(2)
$closure = function ( $withoutDefault, $withDefault = 'default' ) {}; // Bad x 2.
// @mago-expect lint:wordpress/valid-variable-name(2)
$arrow = fn ( $withoutDefault, $withDefault = 'default' ) => 10; // Bad x 2.

// Safeguard recognizing property access using PHP 8.0 nullsafe operator.
// @mago-expect lint:wordpress/valid-variable-name
echo $this?->varName2; // Bad.
echo $this?->var_name2;
echo $this?->varname2;
// @mago-expect lint:wordpress/valid-variable-name
echo $this?->_varName2; // Bad.

// Safeguard handling of PHP 8.1 enums.
enum EnumExample {
	// @mago-expect lint:wordpress/valid-variable-name
	public function method( $paramName ) { // Bad.
		$local_variable = 'OK';
		// @mago-expect lint:wordpress/valid-variable-name
		$localVariable  = 'Bad';
	}
}

// Safeguard ignoring of allowed mixed case property names.
class Has_Mixed_Case_Property {
	public $post_ID; // OK.
}

// Issue #1891 - ensure the sniff does not throw an error if the suggested alternative would be the same as the original name.
$lähtöaika = true; // OK.
// @mago-expect lint:wordpress/valid-variable-name
$lÄhtÖaika = true; // Bad, but only handled by the sniff if Mbstring is available.
// @mago-expect lint:wordpress/valid-variable-name
$lÄhtOaika = true; // Bad, handled via transliteration of non-ASCII chars if Mbstring is not available.
