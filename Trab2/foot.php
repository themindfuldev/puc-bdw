<?php
/*
 This program is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with this program; if not, write to the Free Software
 Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/
?>
			<div id="extra">&nbsp;</div>
      <div id="footer">
<?php
if ($DEBUG) echo '<b>DEBUG enabled.</b> ';
if (defined('PROFILING_ENABLED') && PROFILING_ENABLED && defined($startTime)) {
  list($usec, $sec) = explode(" ", microtime());
  $endTime = ((float)$usec + (float)$sec);
  echo 'Page loaded in '. sprintf('%.1f',($endTime-$startTime)*1000) .' ms.<br />';
}
?>
<a href="http://rnews.sourceforge.net/">Rnews <?php echo RNEWS_VERSION; ?></a> distributed under the <a href="http://www.gnu.org/licenses/gpl.html">GPL</a>. Valid <a href="http://validator.w3.org/check?uri=referer">XHTML</a>, <a href="http://jigsaw.w3.org/css-validator/check/referer">CSS</a>.<br />Copyright of all Feed Content remains with the producer. 
			</div>
		</div>
	</body>
</html>
