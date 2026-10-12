<?php
/**
 * File.
 *
 * @package Fixture
 */

namespace Fixture;

/**
 * Adjacent.
 */
class Adjacent {
}

// @mago-expect lint:generic/class-comment
/**
 * Blank line.
 */

class Gap {
}

// @mago-expect lint:generic/class-comment
/**
 * Blank line before a modifier.
 */

final class Final_Gap {
}

// @mago-expect lint:generic/class-comment
/**
 * Modifier on its own line: phpcs compares the class keyword's line.
 */
final
class Modifier_Line {
}

/**
 * Interfaces are not checked.
 */

interface Not_A_Class {
}
