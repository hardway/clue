<?php  
	class Clue_UI_Pagination{
		public $page, $pageSize, $size;
		protected $pageCount;
		
		function __construct($options){
			$this->page=$options["page"];
			$this->pageSize=$options["pageSize"];
			$this->size=$options["size"];
			
			$this->pageCount=ceil($this->size / $this->pageSize);
		}
		
		// range:String for controller usage
		// eg. 1-20
		function limitRange(){
			$begin=($this->page-1) * $this->pageSize;
			$end=($this->page) * $this->pageSize;
			if($end>$this->size) $end=$this->size;
			
			$size=$end - $begin;
			
			return "limit $begin, $size";
		}
		
		function render($urlPattern, $insertPoint="(?)"){
			if($this->pageCount==1) return;
			
			if($this->page>1){
				$prev=$this->page - 1;
				$prevUrl=str_replace($insertPoint, "$prev", $urlPattern);
				$prevLink="<a class='prev' href='$prevUrl'>&lt;&lt; 前一页</a>";
			}
			else
				$prevLink="";
			
			if($this->page<$this->pageCount){
				$next=$this->page + 1;
				$nextUrl=str_replace($insertPoint, "$next", $urlPattern);
				$nextLink="<a class='next' href='$nextUrl'>后一页 &gt;&gt;</a>";
			}
			else
				$nextLink="";
			
			$links="";
			for($p=1; $p<=$this->pageCount; $p++){
				if($p==$this->page){
					$links.="<a class='current'>$p</a>";
				}
				else{
					$url=str_replace($insertPoint, "$p", $urlPattern);
					$links.="<a class='link' href='$url'>$p</a>";
				}
			}
			
			echo <<<END
		<div class='pagination'>
			$prevLink
			$links
			$nextLink
		</div>
END;
		}
	}
?>