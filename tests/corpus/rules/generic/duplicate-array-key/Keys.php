<?php

/*
 * These will not be analysed as determining runtime value of variables, constants and function
 * calls is not supported.
 */
$excluded = [
    $var                        => 'excluded',
    MY_CONSTANT                 => 'excluded',
    PHP_INT_MAX                 => 'excluded',
    str_replace('.', '', '1.1') => 'excluded',
    self::CONSTANT              => 'excluded',
    $obj->get_key()             => 'excluded',
    $obj->prop                  => 'excluded',
    "my $var text"              => 'excluded',
    <<<EOD
my $var text
EOD
                                => 'excluded',
];

/*
 * Let's find some duplicates.
 */

$emptyStringKey = array(
    ''             => 'empty',
    // All below will error.
// @mago-expect lint:generic/duplicate-array-key
    null           => 'null',
// @mago-expect lint:generic/duplicate-array-key
    (string) false => 'false',
);

$everythingZero = [
    '0',
    // All below will error.
// @mago-expect lint:generic/duplicate-array-key
    0                => 'a',
// @mago-expect lint:generic/duplicate-array-key
    0.0              => 'b',
// @mago-expect lint:generic/duplicate-array-key
    '0'              => 'c',
// @mago-expect lint:generic/duplicate-array-key
    0b0              => 'd',
// @mago-expect lint:generic/duplicate-array-key
    0x0              => 'e',
// @mago-expect lint:generic/duplicate-array-key
    00               => 'f',
// @mago-expect lint:generic/duplicate-array-key
    false            => 'g',
// @mago-expect lint:generic/duplicate-array-key
    0.4              => 'h',
// @mago-expect lint:generic/duplicate-array-key
    -0.8             => 'i',
// @mago-expect lint:generic/duplicate-array-key
    0e0              => 'j',
// @mago-expect lint:generic/duplicate-array-key
    0_0              => 'k',
// @mago-expect lint:generic/duplicate-array-key
    -1 + 1           => 'l',
// @mago-expect lint:generic/duplicate-array-key
    3 * 0            => 'm',
// @mago-expect lint:generic/duplicate-array-key
    00.00            => 'n',
// @mago-expect lint:generic/duplicate-array-key
    (int) 'nothing'  => 'o',
// @mago-expect lint:generic/duplicate-array-key
    15 > 200         => 'p',
// @mago-expect lint:generic/duplicate-array-key
    "0"              => 'q',
// @mago-expect lint:generic/duplicate-array-key
    0.               => 'r',
// @mago-expect lint:generic/duplicate-array-key
    .0               => 's',
// @mago-expect lint:generic/duplicate-array-key
    (true) ? 0 : 1   => 't',
// @mago-expect lint:generic/duplicate-array-key
    ! \true          => 'u',
];

$everythingOne = [
    '0',
    '1',
    // All below will error.
// @mago-expect lint:generic/duplicate-array-key
    1                => 'a',
// @mago-expect lint:generic/duplicate-array-key
    1.1              => 'b',
// @mago-expect lint:generic/duplicate-array-key
    '1'              => 'c',
// @mago-expect lint:generic/duplicate-array-key
    0b1              => 'd',
// @mago-expect lint:generic/duplicate-array-key
    0x1              => 'e',
// @mago-expect lint:generic/duplicate-array-key
    01               => 'f',
// @mago-expect lint:generic/duplicate-array-key
    true             => 'g',
// @mago-expect lint:generic/duplicate-array-key
    1.2              => 'h',
// @mago-expect lint:generic/duplicate-array-key
    1e0              => 'i',
// @mago-expect lint:generic/duplicate-array-key
    0_1              => 'j',
// @mago-expect lint:generic/duplicate-array-key
    -1 + 2           => 'k',
// @mago-expect lint:generic/duplicate-array-key
    3 * 0.5          => 'l',
// @mago-expect lint:generic/duplicate-array-key
    01.00            => 'm',
// @mago-expect lint:generic/duplicate-array-key
    (int) '1 penny'  => 'n',
// @mago-expect lint:generic/duplicate-array-key
    15 < 200         => 'o',
// @mago-expect lint:generic/duplicate-array-key
    "1"              => 'p',
// @mago-expect lint:generic/duplicate-array-key
    1.               => 'q',
// @mago-expect lint:generic/duplicate-array-key
    001.             => 'r',
// @mago-expect lint:generic/duplicate-array-key
    (true) ? 1 : 0   => 's',
// @mago-expect lint:generic/duplicate-array-key
    ! \false         => 't',
// @mago-expect lint:generic/duplicate-array-key
    (string) true    => 'u',
];

$everythingEleven = [
    11               => 'a',
    // All below will error.
// @mago-expect lint:generic/duplicate-array-key
    11.0             => 'b',
// @mago-expect lint:generic/duplicate-array-key
    '11'             => 'c',
// @mago-expect lint:generic/duplicate-array-key
    0b1011           => 'd',
// @mago-expect lint:generic/duplicate-array-key
    0Xb              => 'e',
// @mago-expect lint:generic/duplicate-array-key
    013              => 'f',
// @mago-expect lint:generic/duplicate-array-key
    11.8             => 'g',
// @mago-expect lint:generic/duplicate-array-key
    1.1e1            => 'h',
// @mago-expect lint:generic/duplicate-array-key
    1_1              => 'i',
// @mago-expect lint:generic/duplicate-array-key
    0_13             => 'j',
// @mago-expect lint:generic/duplicate-array-key
    -1 + 12          => 'k',
// @mago-expect lint:generic/duplicate-array-key
    22 / 2           => 'l',
// @mago-expect lint:generic/duplicate-array-key
    0011.0011        => 'm',
// @mago-expect lint:generic/duplicate-array-key
    (int) '11 lane'  => 'n',
// @mago-expect lint:generic/duplicate-array-key
    "11"             => 'o',
// @mago-expect lint:generic/duplicate-array-key
    11.              => 'p',
// @mago-expect lint:generic/duplicate-array-key
    35 % 12          => 'q',
];

$textualStringKeyVariations = [
    'abc'      => 1,
    'def'      => 2,
    'ghi'      => 3,
    // All below will error.
// @mago-expect lint:generic/duplicate-array-key
    'ab' . 'c' => 4, // Error.
// @mago-expect lint:generic/duplicate-array-key
    <<<EOT
def
EOT
               => 5, // Error.
// @mago-expect lint:generic/duplicate-array-key
    <<< 'NOW'
ghi
NOW
               => 6, // Error.
// @mago-expect lint:generic/duplicate-array-key
    "abc"      => 7, // Error.
];

$testKeepingTrackOfHighestIntKey = array(
    ''         => 'empty',
    'a',                  // Int 0
    'b',                  // Int 1
// @mago-expect lint:generic/duplicate-array-key
    1          => 'c',    // Int 1 - Error.
    5          => 'd',    // Int 5
    'e',                  // Int 6
// @mago-expect lint:generic/duplicate-array-key
    6          => 'f',    // Int 6 - Error.
// @mago-expect lint:generic/duplicate-array-key
    false      => 'g',    // Int 0 - Error.
// @mago-expect lint:generic/duplicate-array-key
    true       => 'h',    // Int 1 - Error.
// @mago-expect lint:generic/duplicate-array-key
    1.1        => 'i',    // Int 1 - Error.
// @mago-expect lint:generic/duplicate-array-key
    6.5        => 'j',    // Int 6 - Error.
// @mago-expect lint:generic/duplicate-array-key
    05         => 'k',    // Int 5 - Error.
// @mago-expect lint:generic/duplicate-array-key
    0_6        => 'l',    // Int 6 - Error. PHP 7.4 octal numeric literal.
// @mago-expect lint:generic/duplicate-array-key
    0x1        => 'm',    // Int 1 - Error.
// @mago-expect lint:generic/duplicate-array-key
    0b0        => 'n',    // Int 0 - Error.
// @mago-expect lint:generic/duplicate-array-key
    null       => 'o',    // Empty string - Error.
    02.6e7     => 'p',    // Int 26000000
    '96'       => 'q',    // Int 96
    'r',                  // Int 26000001
// @mago-expect lint:generic/duplicate-array-key
    '26000001' => 's',    // Int 26000001 - Error.
    '96.3'     => 't',    // String '96.3'
// @mago-expect lint:generic/duplicate-array-key
    1 + 0      => 'u',    // Int 1 - Error.
// @mago-expect lint:generic/duplicate-array-key
    1.1 - 0.5  => 'v',    // Int 0 - Error.
    -1         => 'w',    // Int -1
    'x',                  // Int 26000002
// @mago-expect lint:generic/duplicate-array-key
    '9' . '6'  => 'y',    // Int 96 - Error.
    '1.'       => 'z',    // String '1.'
);

$testPHPLt8VsPHP8 = array(
    -10 => 'a',
    'b',        // PHP < 8: int 0; PHP 8+: -9.
    'c',        // PHP < 8: int 1; PHP 8+: -8.
    -4  => 'd',
    'e',        // PHP < 8: int 2; PHP 8+: -3.
// @mago-expect lint:generic/duplicate-array-key
    -9  => 'f', // Duplicate in PHP 8, not in PHP < 8.
// @mago-expect lint:generic/duplicate-array-key
    -8  => 'g', // Duplicate in PHP 8, not in PHP < 8.
// @mago-expect lint:generic/duplicate-array-key
    -3  => 'h', // Duplicate in PHP 8, not in PHP < 8.
// @mago-expect lint:generic/duplicate-array-key
    0   => 'i', // Duplicate in PHP < 8, not in PHP 8.
// @mago-expect lint:generic/duplicate-array-key
    1   => 'j', // Duplicate in PHP < 8, not in PHP 8.
// @mago-expect lint:generic/duplicate-array-key
    2   => 'k', // Duplicate in PHP < 8, not in PHP 8.
);

// Issue PHPCSExtra#316, PHPCSStandards/PHPCSUtils#619 no false positive for numeric string keys starting/ending with an underscore.
$validStringKeys = array(
    '_1'  => 'value1',
    '_2'  => 'value2',
    '3_'  => 'value3',
    '4_'  => 'value4',
    '_5_' => 'value5',
    '_6_' => 'value6',
);

// A string with an underscore is not an integer key, and "-5" is.
$underscore = ['10_0' => 1, 100 => 2];
// @mago-expect lint:generic/duplicate-array-key
$negative = ['-5' => 1, -5 => 2];
