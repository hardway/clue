<?php  
namespace Clue{
	/**
	 * How to get private key
		 * visit http://delicious.com/{username} , the key can be found at rare of the page (Private RSS Feed).
	 */
	
	/**
	 * Delicious API Help Document
	 * @ref http://delicious.com/help/feeds
	 */
	
	class Clue_API_Delicious{
		private $format;
		private $feedbase;
		
		function __construct($format='json'){
			$this->format=$format;
			$this->feedbase="http://feeds.delicious.com/v2/".$this->format;
		}
		
		private function _retrieve_feed($path=""){
			$creeper=new Clue_Creeper();
			
			$creeper->open($this->feedbase.$path);
			
			if($this->format=='json')
				return json_decode($creeper->content);
			else
				return $creeper->content;
		}
		
		function hot(){
			return $this->_retrieve_feed();
		}
		
		function recent(){
			return $this->_retrieve_feed("/recent");
		}
		
		function recent_tag($tag){
			return $this->_retrieve_feed("/recent/$tag");
		}
		
		function popular(){
			return $this->_retrieve_feed("/popular");
		}
		
		function popular_tag($tag){
			return $this->_retrieve_feed("/popular/$tag");
		}
		
		function alerts(){
			return $this->_retrieve_feed("/alerts");
		}
		
		function bookmarks_of_user($username){
			return $this->_retrieve_feed("/$username");
		}
		
		function bookmarks_of_user_private($username, $key){
			return $this->_retrieve_feed("/$username?private=$key");
		}
		
		function bookmarks_of_user_tags($username, array $tags){
			$tags=is_array($tags) ? implode("+", $tags) : $tags;
			return $this->_retrieve_feed("/$username/$tags");
		}
		
		function bookmarks_of_user_tags_private($username, array $tags, $key){
			$tags=is_array($tags) ? implode("+", $tags) : $tags;
			return $this->_retrieve_feed("/$username/$tags?private=$key");
		}
		
		function user_info($username){
			return $this->_retrieve_feed("/userinfo/$username");
		}
		
		function tags_of_user($username){
			return $this->_retrieve_feed("/tags/$username");
		}
		
		function subscriptions_of_user($username){
			return $this->_retrieve_feed("/subscriptions/$username");
		}
		
		function inbox_of_user($username, $key){
			return $this->_retrieve_feed("/inbox/$username?private=$key");
		}
		
		function network_of_user($username){
			return $this->_retrieve_feed("/network/$username");
		}
		
		function network_of_user_private($username, $key){
			return $this->_retrieve_feed("/network/$username?private=$key");
		}
		
		function network_of_user_tags($username, $tags){
			$tags=is_array($tags) ? implode("+", $tags) : $tags;
			return $this->_retrieve_feed("/network/$username/$tags");
		}
		
		function network_of_user_tags_private($username, $tags, $key){
			$tags=is_array($tags) ? implode("+", $tags) : $tags;
			return $this->_retrieve_feed("/network/$username/$tags?private=$key");
		}
		
		function network_members_of_user($username){
			return $this->_retrieve_feed("/networkmembers/$username");
		}
		
		function network_fans_of_user($username){
			return $this->_retrieve_feed("/networkfans/$username");
		}
		
		function url_recent($md5URL){
			return $this->_retrieve_feed("/url/$md5URL");
		}
		
		function url_info($md5URL){
			return $this->_retrieve_feed("/urlinfo/$md5URL");
		}
	}	
}
?>