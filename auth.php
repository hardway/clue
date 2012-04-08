<?php
namespace Clue{
    interface Auth{
        /**
         * Authenticate with credentials and return basic user info
         *
         * @param string $username 
         * @param string $password 
         * @param string $extra 
         * @return array('id'=>..., 'name'=>...)
         */
        public function authenticate($username, $password, $extra=array());
        
        /**
         * Authorize resource against given identity
         *
         * @param string $identity 
         * @param string $resource 
         * @return void
         * @author houdw
         */
        public function authorize($identity, $resource=null);
    }
}
?>
