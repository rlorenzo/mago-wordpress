<?php

declare(strict_types=1);

// wordpress_style_and_pascal_case_pass
final class Valid_Class_Name {}

final class ValidClassName {}

interface VALID_Interface {}

// a_lowercase_word_after_an_underscore_is_flagged
// @mago-expect lint:generic/valid-class-name
final class Invalid_name {}

// a_lowercase_start_is_flagged_twice_when_the_words_are_wrong_too
// @mago-expect lint:generic/valid-class-name(2)
trait invalid_name {}

// a_leading_underscore_does_not_start_with_a_capital
// @mago-expect lint:generic/valid-class-name
enum _Invalid_Enum {}

// anonymous_classes_have_no_name
$anonymous = new class {};

// phpcs_ignore_silences_it
// phpcs:ignore PEAR.NamingConventions.ValidClassName.Invalid
final class Ignored_name {}
