<?php

add_action(
	'admin_notices',
	function () {
		?>
		<div class="notice">
			<?php
			printf(
				'%s',
				$x
			);
			?>
		</div>
		<?php
	}
);
