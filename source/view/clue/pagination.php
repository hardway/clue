<div class='pagination'><ul>
	<?php if($prevLink): ?>
		<li><a href='<?=$prevLink?>'><i class='icon-chevron-left'></i></a></li>
	<?php endif; ?>

	<?php
		foreach($links as $p=>$link){
			if($p==$currentPage){
				echo "<li class='active'><a>$p</a></li>";
			}
			else{
				echo "<li><a href='$link'>$p</a></li>";
			}
		}
	?>

	<?php if($nextLink): ?>
		<li><a href='<?=$nextLink?>'><i class='icon-chevron-right'></i></a></li>
	<?php endif; ?>
</ul></div>
