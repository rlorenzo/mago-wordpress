<?php

// Bad: hyphenated lowercase, but a file with a class needs a `class-` prefix.
// @mago-expect lint:wordpress/file-name
class My_Class {
}
