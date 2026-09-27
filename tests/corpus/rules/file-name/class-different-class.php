<?php

// Bad: `class-` prefix present, but the rest does not match the class name.
// @mago-expect lint:wordpress/file-name
class Something_Else {
}
