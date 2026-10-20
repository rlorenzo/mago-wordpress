<?php
// A comment between the opening tag and the docblock does not stop it being a file comment.

// @mago-expect lint:generic/doc-comment-alignment(2)
/**
* @todo Parse the file.
*/

class Aligned_With_Tabs {
    /**
     * Tab and space before the star count as the same four columns as spaces.
	 * @var array
     */
    public $items = array();
}
