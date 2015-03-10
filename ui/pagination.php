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
			$this->size=$total;
			$this->pageSize=isset($options["pageSize"]) ? $options['pageSize'] : 10;
			$this->navPages=isset($options["navPages"]) ? $options['navPages'] : 10;

			$this->pageCount=ceil($this->size / $this->pageSize);

			// 确保page始终在有效范围内
			$this->page=max(1, min($page, $this->pageCount));
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

		function slice_array(&$array){
			$ret=array_slice($array, ($this->page - 1)*$this->pageSize, $this->pageSize);
			return $ret;
		}

		// slice: used for array_slice
		function sliceOffset(){
			return ($this->page-1) * $this->pageSize;
		}

		function sliceLength(){
			return $this->pageSize;
		}

		protected function page_url($page, $url_option){
			if(isset($url_option['url_pattern'])){
				return str_replace('{page}', $page, $url_option['url_pattern']);
			}
			elseif(isset($url_option['url_param'])){
				$path=parse_url($_SERVER['REQUEST_URI'])['path'];
				parse_str($_SERVER['QUERY_STRING'], $params);
				$params[$url_option['url_param']]=$page;
				return $path.'?'.http_build_query($params);
			}
		}

		/**
		 * 輸出使用Bootstrap結構的HTML
		 * @param $url_option={url_param:'p', url_pattern:'something/p/{page}'}
		 */
		function render($url_option=['url_param'=>'p']){
			if($this->pageCount==1) return;

			if($this->page>1){
				$prevLink="<li><a href='".$this->page_url($this->page - 1, $url_option)."'><i class='icon-chevron-left'></i></a></li>";
			}
			else
				$prevLink="";

			if($this->page<$this->pageCount){
				$nextLink="<li><a href='".$this->page_url($this->page + 1, $url_option)."'><i class='icon-chevron-right'></i></a></li>";
			}
			else
				$nextLink="";

			$links="";
			$begin=$this->page > $this->navPages/2 ? floor($this->page - $this->navPages/2) : 1;
			$end=$this->page + $this->navPages/2 > $this->pageCount ? $this->pageCount : floor($this->page + $this->navPages/2);

			for($p=$begin; $p<=$end; $p++){
				if($p==$this->page){
					$links.="<li class='active'><a>$p</a></li>";
				}
				else{
					$links.="<li><a href='".$this->page_url($p, $url_option)."'>$p</a></li>";
				}
			}

			echo "
				<div class='pagination'><ul>
					$prevLink
					$links
					$nextLink
				</ul></div>
			";
		}
	}
}
?>
