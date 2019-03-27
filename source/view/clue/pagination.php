<ul class='pagination'>
  <?php if($prevLink): ?>
    <li class='page-item'><a href='<?=$prevLink?>'>Prev</i></a></li>
  <?php endif; ?>

  <?php
    foreach($links as $p=>$link){
      if($p==$currentPage){
        echo "<li class='page-item active'><a>$p</a></li>";
      }
      else{
        echo "<li class='page-item'><a href='$link'>$p</a></li>";
      }
    }
  ?>

  <?php if($nextLink): ?>
    <li class='page-item'><a href='<?=$nextLink?>'>Next</i></a></li>
  <?php endif; ?>
</ul>
