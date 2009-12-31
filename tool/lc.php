<?php
/**
 * List comprehensions for PHP (php-lc)
 * @version 0.13
 * @author Vlad Andersen <vlad.andersen@gmail.com>
 * @link http://code.google.com/p/php-lc/
 * @license GPL
 * 
 * With php-lc you can easily manipulate your PHP arrays in style of Python list comprehensions.
 * 
 * Synopsis:
 * 
 * %<a href="/data/%s/">%s</a> % strtolower ($value), $value for $value in $Data
 * 
 * lc ('$i*2 for $i in $Data if $i > 5', compact ('Data'))
 * 
 * {substr ($value, 0, 1) => $value} for $value in $Data
 * 
 * 
 * == Basic syntax ==
 * 
 * The syntax for php-lc expressions is the following:
 *    <return> for [<key> => ]<element> in <Data> [if <condition>]
 * <return> could be any expression that is using <element>, <key> (if provided, discussed below) 
 * or any of the passed variables. If no <key> is provided, php-lc will return an array with consecutive
 * numeric indexes (a list).
 *
 * @param string $expression List comprehension expression
 * @param array $Data List comprehension variables
 * @return array
 * See what you can do with php-lc on: http://code.google.com/p/php-lc/
 */

/*
	Other Examples:
	
	$Foo = array (1, 2, 3, 4, 5, 6, 7, 8, 9, 10);
	print_r(lc ('$i*2 for $i in $Foo if $i > 5', compact ('Foo')));
	
	$Foo = array ('Alabama', 'California', 'Texas', 'New York');
	print_r (lc ('strtoupper($state) for $state in $Foo if strlen ($state) > 5 and strlen ($state) < 9', array ('Foo' => $Foo)));
	
	$Foo = array ('AL' => 'Alabama', 'CA' => 'California', 'TE' => 'Texas', 'NY' => 'New York');
	print_r (lc ('{strtoupper($state) => strtolower ($code)} for $code => $state in $Foo if strlen ($state) > 5 and strlen ($state) < 9', array ('Foo' => $Foo)));
	
	$Foo = array (
		array ('name' => 'David Beckham', 'number' => 7),
		array ('name' => 'Ronaldinho', 'number' => 10),
		array ('name' => 'David Villa', 'number' => 9)
	);
	print_r (lc ('$player["number"] => preg_replace (\'#^[A-z]+\s#i\', "", $player["name"]) for $player in $Foo', array ('Foo' => $Foo)));
	
	$Foo = array (
		array ('title' => 'Brad Pitt', 'id' => "nm0000093"),
		array ('title' => 'Al Pacino', 'id' => "nm0000199"),
		array ('title' => 'Keanu Reeves', 'id' => "nm0000206")
	);
	print_r (lc ('%<a href="http://www.imdb.com/%s">%s</a> % $actor["id"], $actor["title"] for $actor in $Foo', array ('Foo' => $Foo)));
	
	$Foo = array (
		array ('name' => 'Beckham', 'number' => 7),
		array ('name' => 'Ronaldinho', 'number' => 10),
		array ('name' => 'Arshavin', 'number' => 10),
		array ('name' => 'Raul', 'number' => 7),
		array ('name' => 'Villa', 'number' => 9)
	);
	print_r (lc ('$player["number"] => [$player["name"]] for $player in $Foo', array ('Foo' => $Foo)));
*/

class ListComprehension {
	private $expression = '';
	private $Variables = array();
	
	const GLOBAL_ID = '__listcomprehension';

	/**
	 * See description of the lc function above.
	 *
   * @param string $expression List comprehension expression
   * @param array $Data List comprehension variables
   * @return array
 	 */
	public static function execute ($expression, $Data = array()) {
		$ListComprehension = new self ($expression, $Data);
    return $ListComprehension->evaluate();
	}
	
	private function __construct ($expression, $Data = array()) {
		$this->expression = $expression;
		$this->Variables = $Data;
	}
	
	private function evaluate() {
		$Object = array(); $IteratorMatches = array(); $ReturnMatches = array();
		if (!preg_match('#^\s*(.+?)\s+for\s+(.+?)\s+in\s+([^\[\]]+?)(\s+if\s+(.+?))?\s*$#ims', $this->expression, $Object)) return false;
    // print_r ($Object);
		
		$LCObject = new ListComprehensionObject();
		$LCObject->Iterable = $this->Variables[ltrim($Object[3], '$')];
		$LCObject->condition = isset ($Object[5]) ? $Object[5] : true;
		$LCObject->Variables = $this->Variables;
		$LCObject->iteratorName = $Object[2];
		if (preg_match ('#(.+)=>(.+)#', $LCObject->iteratorName, $IteratorMatches)) {
			$LCObject->iteratorName = trim ($IteratorMatches[2]);
			$LCObject->iteratorKeyName = trim ($IteratorMatches[1]);
		}
		
		$LCObject->return = trim($Object[1]);
		if (preg_match ('#^%(.+) % (.+)$#', $LCObject->return, $Matches)) {
			$LCObject->return = "sprintf ('" . $Matches[1] . "', " . $Matches[2] . ')';
		}
		if (preg_match ('#=>#', $LCObject->return)) {
		  if (!preg_match ('#array#i', $LCObject->return)) {
		    $LCObject->return = sprintf ('{%s}', trim ($LCObject->return, '{}'));
		  }
		  if (preg_match ('#^{(.+?)=>(.+)}$#', $LCObject->return, $ReturnMatches)) {
			  $LCObject->return = trim ($ReturnMatches[2]);
			  $LCObject->returnKey = trim ($ReturnMatches[1]);
		  }
      if (preg_match ('#^\[.+\]$#', $LCObject->return)) {
        $LCObject->return = substr ($LCObject->return, 1, -1);
        $LCObject->optionReturnArrayInHash = true;
      }
		}
		
		return $LCObject->run();
	} 
	
}

/**
 * The class for running list comprehension objects. Do not call directly.
 */
class ListComprehensionObject {
	public $Iterable = array();
	public $condition = '';
	public $Variables = array();
	public $iteratorName;
	public $iteratorKeyName = '';
	public $return;
	public $returnKey = '';
  /**
   * @var bool True if syntax of $k => [$v] used.
   */
  public $optionReturnArrayInHash = false;
	
	private $currentIterator;
	
	public function run() {
		if (!is_array ($this->Iterable)) return array();
		$GLOBALS[ListComprehension::GLOBAL_ID] = $this->Variables;
		//print $filterExpression;
		if (!$this->iteratorKeyName) {
			$filterExpression = 'extract ($GLOBALS["'.ListComprehension::GLOBAL_ID.'"]); return (' . $this->condition . ');';
			$filterFunction = create_function($this->iteratorName, $filterExpression);
			$this->Iterable = array_filter($this->Iterable, $filterFunction);
		} else {
			$filterExpression = 'extract ($GLOBALS["'.ListComprehension::GLOBAL_ID.'"]); if (' . $this->condition . ') return array (' .
                          $this->iteratorKeyName . ' => ' . $this->iteratorName . ');';
			$filterFunction = create_function($this->iteratorKeyName . ',' . $this->iteratorName, $filterExpression);
			$this->Iterable = array_map($filterFunction, array_keys($this->Iterable), $this->Iterable);
			$this->Iterable = array_filter ($this->Iterable);
			$Data = array();
			foreach ($this->Iterable as $Arr)
		      $Data[key($Arr)] = current($Arr);
			$this->Iterable = $Data;
		}

		if (!$this->returnKey) {
			$returnExpression = 'extract ($GLOBALS["'.ListComprehension::GLOBAL_ID.'"]); return (' . $this->return . ');';
		} else {
			$returnExpression = 'extract ($GLOBALS["'.ListComprehension::GLOBAL_ID.'"]); return array (' . $this->returnKey . ' => ' . $this->return . ');';
		}
			
		if (!$this->iteratorKeyName) {
		  $returnFunction = create_function($this->iteratorName, $returnExpression);
      if (!is_callable($returnFunction))
        die ("Failed to execute the following lc-expression: " . $returnExpression);
		  $this->Iterable = array_map($returnFunction, $this->Iterable);
		} else {
		  $returnFunction = create_function($this->iteratorKeyName . ',' . $this->iteratorName, $returnExpression);
		  $this->Iterable = array_map($returnFunction, array_keys($this->Iterable), $this->Iterable);
		}
		
		unset ($GLOBALS[ListComprehension::GLOBAL_ID]);
		
		if (!$this->returnKey)   
			return array_values($this->Iterable);
			
	  $Data = array();
		foreach ($this->Iterable as $Arr) {
      if (!$this->optionReturnArrayInHash) {
        $Data[key($Arr)] = current($Arr);
        continue;
      }
      // If option return array in hash.
      if (!isset ($Data[key($Arr)]))
        $Data[key($Arr)] = array();
      $Data[key($Arr)][] = current($Arr);
    }

    $this->Iterable = $Data;
	  return $this->Iterable;
	}
}
?>