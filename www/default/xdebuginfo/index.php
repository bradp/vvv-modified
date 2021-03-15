<?php
/**
 * Output the phpinfo for our setup
 */
?>
<nav class="center">
	<a href="//vvv.test/phpinfo/">PHP Info</a>
	<?php
	if ( function_exists( 'xdebug_info' ) ) {
		?>, <a href="//vvv.test/xdebuginfo/">Xdebug Info</a><?php
	}
	?>
</nav>
<hr/>
<?php
if ( function_exists( 'xdebug_info' ) ) {
	echo '<div id="xdebuginfo">';
	xdebug_info();
	echo '</div>';
}
