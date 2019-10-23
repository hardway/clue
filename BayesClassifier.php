<?php
class EnglishTokenizer{
    // Common stop words
    // REF: https://nlp.stanford.edu/IR-book/html/htmledition/dropping-common-terms-stop-words-1.html
    static $STOP_WORDS=[
        'a', 'an', 'and', 'are', 'as', 'at', 'be', 'by', 'for', 'from',
        'has', 'he', 'in', 'is', 'it', 'its', 'of', 'on', 'that', 'the',
        'to', 'was', 'were', 'will', 'with'
    ];

    // Spamassassin stoplist
    // REF: http://wiki.apache.org/spamassassin/BayesStopList
    static $STOP_REGEXP='/[^\w]+|able|all|already|and|any|are|because|both|can|come|each|email|even|few|first|for|from|give|has|have|http|information|into|it\'s|just|know|like|long|look|made|mail|mailing|mailto|make|many|more|most|much|need|not|now|number|off|one|only|out|own|people|place|right|same|see|such|that|the|this|through|time|using|web|where|why|with|without|work|world|year|years|you|you\'re|your/';

    protected $pattern = "/[ ,.?!-:;\\n\\r\\t…_|]/u";

    function tokenize($string){
        $tokens=[];

        foreach(preg_split($this->pattern, mb_strtolower($string, 'utf8')) as $t){
            $t=trim($t);
            if(strlen($t)==0) continue;
            if(in_array($t, self::$STOP_WORDS)) continue;

            @$tokens[$t]++;
        }

        return $tokens;
    }
}

// TODO: 大规模数据使用redis或者memcache
class BayesClassifier{
    protected $tokenizer;

    protected $labels;
    protected $docs;
    protected $tokens;
    protected $data;

    public function __construct($tokenizer=null){
        $this->tokenizer = $tokenizer ?: new EnglishTokenizer();
        $this->reset();
    }

    /**
     * 文本/标签训练
     *
     * @param string $label
     * @param string $text
     */
    public function train($label, $text){
        $tokens = $this->tokenizer->tokenize($text);

        if (!isset($this->labels[$label])) {
            $this->labels[$label] = 0;
            $this->data[$label] = [];
            $this->docs[$label] = 0;
        }

        foreach ($tokens as $t=>$c) {
            $this->labels[$label]+=$c;
            @$this->tokens[$t]+=$c;
            @$this->data[$label][$t]+=$c;
        }

        $this->docs[$label]++;
    }

    /**
     * Bayes公式
     * P(h|D) = P(D|h) * P(h) / P(D)
     *
     * @param  string $text
     * @return array
     */
    public function classify($text){
        $tokens = $this->tokenizer->tokenize($text);
        $scores = [];

        foreach ($this->labels as $label => $cnt_label) {
            $sum = [];

            // 出现Label的概率
            $PoL=$this->docs[$label] / array_sum($this->docs);

            foreach ($tokens as $token=>$c) {
                // 出现Token的概率
                if(!isset($this->tokens[$token])) continue;
                $PoT=$this->tokens[$token] / array_sum($this->tokens);

                // 该Label出现Token的概率
                $PoL2T=@$this->data[$label][$token] / array_sum($this->data[$label]);

                $PoT2L=$PoL * $PoL2T / $PoT;

                // 防止极端偏差
                if($PoT2L===0) $PoT2L=0.0001;
                if($PoT2L===1) $PoT2L=0.9999;

                // TODO: 使用对数?
                $sum[]=$PoT2L;
            }

            $scores[$label]=array_sum($sum) / count($sum);
        }

        arsort($scores, SORT_NUMERIC);

        return $scores;
    }

    /**
     * 简单
     * 参考shield
     */
    public function classify2($text){
        $tokens = $this->tokenizer->tokenize($text);
        $scores = [];

        foreach ($this->labels as $label => $cnt_label) {
            $sum = 0;

            foreach ($tokens as $token=>$c) {
                // 不使用先验概率
                // 只使最大似然：Label中该Token出现的概率进行计算
                $P=@$this->data[$label][$token] / array_sum($this->data[$label]);

                // 防止极端偏差
                if($P===0) $P=0.0001;
                if($P===1) $P=0.9999;

                // 使用对数
                $sum+=log($P);
            }

            $scores[$label]=$sum;
        }

        // 归一化显示为百分比
        $max=max($scores);
        $min=min($scores);
        foreach($scores as $c=>$s){
            if($min==$max){
                $scores[$c]=1;
            }
            else{
                $scores[$c]=($s - $min) / ($max - $min);
            }
        }

        arsort($scores, SORT_NUMERIC);

        return $scores;
    }

    public function reset(){
        $this->labels = [];
        $this->docs = [];
        $this->tokens = [];
        $this->data = [];
    }
}
