<?php
namespace Clue\UI{
	class Pagination{
		public $page, $pageSize, $size;
		public $navPages;
		protected $pageCount;

		/**
		 * @param $page 	当前页面
		 * @param $total 	总记录数
		 * @param $options
		 *			pageSize 每页多少记录，默认为10
		 *			navPages 导航显示多少页，默认为10
		 */
		function __construct($page, $total, $options=array()){
			$this->page=$page;
			$this->size=$total;
			$this->pageSize=isset($options["pageSize"]) ? $options['pageSize'] : 10;
			$this->navPages=isset($options["navPages"]) ? $options['navPages'] : 10;

			$this->pageCount=ceil($this->size / $this->pageSize);
		}

		// range:String for sql usage
		// eg. 1-20
		function limit_range(){
		    $begin=($this->page-1) * $this->pageSize;
			$end=min($this->size, $begin + $this->pageSize);

			$size=$end - $begin;

			return "limit $begin, $size";
		}

		function item_range(){
			$begin=($this->page-1) * $this->pageSize;
			$end=($this->page) * $this->pageSize;
			return range($begin, $end - 1);
		}

		// slice: used for array_slice
		function sliceOffset(){
			return ($this->page-1) * $this->pageSize;
		}

		function sliceLength(){
			return $this->pageSize;
		}

		function render($urlPattern, $insertPoint="{page}"){
			if($this->pageCount==1) return;

			if($this->page>1){
				$prev=$this->page - 1;
				$prevUrl=str_replace($insertPoint, "$prev", $urlPattern);
				$prevLink="<a class='prev icon-prev' href='$prevUrl'></a>";
			}
			else
				$prevLink="";

			if($this->page<$this->pageCount){
				$next=$this->page + 1;
				$nextUrl=str_replace($insertPoint, "$next", $urlPattern);
				$nextLink="<a class='next icon-next' href='$nextUrl'></a>";
			}
			else
				$nextLink="";

			$links="";
			$begin=$this->page > 10 ? $this->page - 10 : 1;
			$end=$this->page+9 > $this->pageCount ? $this->pageCount : $this->page+9;
			for($p=$begin; $p<=$end; $p++){
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
}
?>
