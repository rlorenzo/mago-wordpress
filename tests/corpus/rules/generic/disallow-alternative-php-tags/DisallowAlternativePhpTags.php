<?php

declare(strict_types=1);

// asp_and_script_tags_in_html_are_flagged
// @mago-expect lint:generic/disallow-alternative-php-tags(3)
?>
<div>
<% echo $title; %>
<%= $title %>
<script language="php">echo $title;</script>
<p>plain html is fine</p>
<script type="text/javascript">var x = 1;</script>
</div>
<?php

echo 'done';
