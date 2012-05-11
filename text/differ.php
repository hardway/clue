<?php  
namespace Clue\Text{
    class Differ{
        /* 
            Copyright: http://www.holomind.de/phpnet/diff2.php 
        */
        static function diff($old,$new) 
        {
            # split the source text into arrays of lines
            $t1 = explode("\n",$old);
            $t2 = explode("\n",$new);
            
            # build a reverse-index array using the line as key and line number as value
            # don't store blank lines, so they won't be targets of the shortest distance
            # search
            foreach($t1 as $i=>$x) if ($x>'') $r1[$x][]=$i;
            foreach($t2 as $i=>$x) if ($x>'') $r2[$x][]=$i;
            
            $a1=0; $a2=0;   # start at beginning of each list
            $actions=array();
            
            # walk this loop until we reach the end of one of the lists
            while ($a1<count($t1) && $a2<count($t2)) {
             # if we have a common element, save it and go to the next
             if ($t1[$a1]==$t2[$a2]) { $actions[]=4; $a1++; $a2++; continue; } 
            
             # otherwise, find the shortest move (Manhattan-distance) from the
             # current location
             $best1=count($t1); $best2=count($t2);
             $s1=$a1; $s2=$a2;
             while(($s1+$s2-$a1-$a2) < ($best1+$best2-$a1-$a2)) {
               $d=-1;
               if(isset($t2[$s2]) && isset($r1[$t2[$s2]])) foreach((array)$r1[$t2[$s2]] as $n) 
                 if ($n>=$s1) { $d=$n; break; }
               if ($d>=$s1 && ($d+$s2-$a1-$a2)<($best1+$best2-$a1-$a2))
                 { $best1=$d; $best2=$s2; }
               $d=-1;
               if(isset($t1[$s1]) && isset($r2[$t1[$s1]]))foreach((array)$r2[$t1[$s1]] as $n) 
                 if ($n>=$s2) { $d=$n; break; }
               if ($d>=$s2 && ($s1+$d-$a1-$a2)<($best1+$best2-$a1-$a2))
                 { $best1=$s1; $best2=$d; }
               $s1++; $s2++;
             }
             while ($a1<$best1) { $actions[]=1; $a1++; }  # deleted elements
             while ($a2<$best2) { $actions[]=2; $a2++; }  # added elements
            }
            
            # we've reached the end of one list, now walk to the end of the other
            while($a1<count($t1)) { $actions[]=1; $a1++; }  # deleted elements
            while($a2<count($t2)) { $actions[]=2; $a2++; }  # added elements
            
            # and this marks our ending point
            $actions[]=8;
            
            # now, let's follow the path we just took and report the added/deleted
            # elements into $out.
            $op = 0;
            $x0=$x1=0; $y0=$y1=0;
            $out = array();
            foreach($actions as $act) {
            if ($act==1) { $op|=$act; $x1++; continue; }
            if ($act==2) { $op|=$act; $y1++; continue; }
            if ($op>0) {
              $xstr = ($x1==($x0+1)) ? $x1 : ($x0+1).",$x1";
              $ystr = ($y1==($y0+1)) ? $y1 : ($y0+1).",$y1";
              if ($op==1) $out[] = "{$xstr}d{$y1}";
              elseif ($op==3) $out[] = "{$xstr}c{$ystr}";
              while ($x0<$x1) { $out[] = '< '.$t1[$x0]; $x0++; }   # deleted elems
              if ($op==2) $out[] = "{$x1}a{$ystr}";
              elseif ($op==3) $out[] = '---';
              while ($y0<$y1) { $out[] = '> '.$t2[$y0]; $y0++; }   # added elems
            }
            $x1++; $x0=$x1;
            $y1++; $y0=$y1;
            $op=0;
            }
            $out[] = '';
            return join("\n",$out);
        } 
    
        static function patch($text, $diff){
            $old=explode("\n",$text);
            $op=0;
            
            $new=array();
            
            $diff=explode("\n", $diff);
            $di=0;
            while($di<count($diff)){
                $action=$diff[$di];
                $di++;
                if(preg_match('/([0-9,]+)(a|c|d)([0-9,]+)/', $action, $match)){
                    list($b1, $e1)= strpos($match[1], ',')>0 ? explode(',', $match[1]) : array($match[1], $match[1]);
                    $len1=$e1-$b1+1;
                    
                    list($b2, $e2)= strpos($match[3], ',')>0 ? explode(',', $match[3]) : array($match[3], $match[3]);
                    $len2=$e2-$b2+1;
                    
                    $action=$match[2];
                    switch($action){
                        case 'a':
                            // printf("Add: %d lines at line[%d]\n", $len2, $b1);
                            
                            for($i=$op; $i<$b1; $i++) $new[]=$old[$i];
                            
                            for($i=0; $i<$len2; $i++) $new[]=substr($diff[$di++], 2);
                            
                            $op=$e1;
                            
                            break;
                        case 'd':
                            // printf("Delete: %d lines from line[%d]\n", $len1, $b1);
                            
                            for($i=$op; $i<$b1-1; $i++) $new[]=$old[$i];
                            
                            $di+=$len1;
                            
                            $op=$e1;
                            break;
                        case 'c':
                            // printf("Change: %d lines from line[%d] with %d lines\n", $len1, $b1, $len2);
                            
                            for($i=$op; $i<$b1-1; $i++) $new[]=$old[$i];
                            
                            $di+=$len1+1;
                            
                            for($i=0; $i<$len2; $i++) $new[]=substr($diff[$di++], 2);
                            
                            $op=$e1;
                    }
                }
            }
            for($i=$op; $i<count($old); $i++){
                $new[]=$old[$i];
            }
            return implode("\n", $new);
        }
    }
}
?>