<?php
class Yaml
{
    const REMPTY = "\0\0\0\0\0";

    /**
     * Setting this to true will force YAMLDump to enclose any string value in
     * quotes.  False by default.
     *
     * @var bool
     */
    public $settingDumpForceQuotes = false;

    /**
     * Setting this to true will forse YAMLLoad to use syck_load function when
     * possible. False by default.
     *
     * @var bool
     */
    public $settingUseSyckIsPossible = false;

    /**
     * Private vars.
     * @var mixed
     */
    private $dumpIndent;
    private $dumpWordWrap;
    private $containsGroupAnchor = false;
    private $containsGroupAlias = false;
    private $path;
    private $result;
    private $LiteralPlaceHolder = '___YAML_Literal_Block___';
    private $SavedGroups = array();
    private $indent;

    /**
     * Path modifier that should be applied after adding current element.
     * @var array
     */
    private $delayedPath = array();

    /**#@+
     * @access public
     * @var mixed
     */
    public $nodeId;

    /**
     * Yaml Construct
     * @param string $file (alternative) path of yaml file
     */
    public function __construct($file = '')
    {
        if (!empty($file)) {
            $this->load($file);
        }
    }

    /**
     * Load a yaml file & parse
     *
     * @param  string $file path of yaml file
     * @return array        yaml parsed result
     */
    public function load($file)
    {
        return $this->loadWithSource($this->loadFromFile($file));
    }

    /**
     * Load a yaml string & parse
     *
     * @param  string $yamlContent string conatining yaml content
     * @return array               yaml parsed result
     */
    public function loadString($yamlContent)
    {
        return $this->loadWithSource($this->loadFromString($yamlContent));
    }

    /**
     * Load a valid YAML file to Spyc.
     * @param string $file
     * @return array
     */
    public function loadFile($file)
    {
        return $this->load($file);
    }

    /**
     * Dump PHP array to YAML
     *
     * The dump method, when supplied with an array, will do its best
     * to convert the array into friendly YAML.  Pretty simple.  Feel free to
     * save the returned string as tasteful.yaml and pass it around.
     *
     * Oh, and you can decide how big the indent is and what the wordwrap
     * for folding is.  Pretty cool -- just pass in 'false' for either if
     * you want to use the default.
     *
     * Indent's default is 2 spaces, wordwrap's default is 40 characters.  And
     * you can turn off wordwrap by passing in 0.
     *
     * @access public
     * @return string
     * @param array $array PHP array
     * @param int $indent Pass in false to use the default, which is 2
     * @param int $wordwrap Pass in 0 for no wordwrap, false for default (40)
     */
    public function dump($array, $indent = false, $wordwrap = false)
    {
        // Dumps to some very clean YAML.  We'll have to add some more features
        // and options soon.  And better support for folding.

        // New features and options.
        if ($indent === false or !is_numeric($indent)) {
            $this->dumpIndent = 2;
        } else {
            $this->dumpIndent = $indent;
        }

        if ($wordwrap === false or !is_numeric($wordwrap)) {
            $this->dumpWordWrap = 40;
        } else {
            $this->dumpWordWrap = $wordwrap;
        }

        // New YAML document
        $string = "---\n";

        // Start at the base of the array and move through it.
        if ($array) {
            $array = (array)$array;
            $previous_key = -1;

            foreach ($array as $key => $value) {
                if (!isset($first_key)) {
                    $first_key = $key;
                }
                $string .= $this->yamlize($key,$value,0,$previous_key, $first_key, $array);
                $previous_key = $key;
            }
        }
        return $string;
    }

    /**
     * Attempts to convert a key / value array item to YAML
     * @access private
     * @return string
     * @param $key The name of the key
     * @param $value The value of the item
     * @param $indent The indent of the current node
     */
    private function yamlize($key, $value, $indent, $previous_key = -1, $first_key = 0, $source_array = null)
    {
        if (is_array($value)) {
            if (empty ($value)) {
                return $this->dumpNode($key, array(), $indent, $previous_key, $first_key, $source_array);
            }
            // It has children.  What to do?
            // Make it the right kind of item
            $string = $this->dumpNode($key, self::REMPTY, $indent, $previous_key, $first_key, $source_array);
            // Add the indent
            $indent += $this->dumpIndent;
            // Yamlize the array
            $string .= $this->yamlizeArray($value,$indent);
        } elseif (!is_array($value)) {
            // It doesn't have children.  Yip.
            $string = $this->dumpNode($key, $value, $indent, $previous_key, $first_key, $source_array);
        }

        return $string;
    }

    /**
     * Attempts to convert an array to YAML
     * @access private
     * @return string
     * @param $array The array you want to convert
     * @param $indent The indent of the current level
     */
    private function yamlizeArray($array, $indent)
    {
        if (is_array($array)) {
            $string = '';
            $previous_key = -1;

            foreach ($array as $key => $value) {
                if (!isset($first_key)) {
                    $first_key = $key;
                }

                $string .= $this->yamlize($key, $value, $indent, $previous_key, $first_key, $array);
                $previous_key = $key;
            }

            return $string;
        } else {
            return false;
        }
    }

    /**
     * Returns YAML from a key and a value
     * @access private
     * @return string
     * @param $key The name of the key
     * @param $value The value of the item
     * @param $indent The indent of the current node
     */
    private function dumpNode($key, $value, $indent, $previous_key = -1, $first_key = 0, $source_array = null)
    {
        // do some folding here, for blocks
        if (is_string ($value) && ((strpos($value,"\n") !== false || strpos($value,": ") !== false ||
            strpos($value,"- ") !== false || strpos($value,"*") !== false || strpos($value,"#") !== false ||
            strpos($value,"<") !== false || strpos($value,">") !== false || strpos ($value, '  ') !== false ||
            strpos($value,"[") !== false || strpos($value,"]") !== false || strpos($value,"{") !== false ||
            strpos($value,"}") !== false) || strpos($value,"&") !== false || strpos($value, "'") !== false ||
            strpos($value, "!") === 0 || substr ($value, -1, 1) == ':')
        ) {
            $value = $this->doLiteralBlock($value,$indent);
        } else {
            $value  = $this->doFolding($value,$indent);
        }

        if ($value === array()) {
            $value = '[ ]';
        }
        if (in_array ($value, array ('true', 'TRUE', 'false', 'FALSE', 'y', 'Y', 'n', 'N', 'null', 'NULL'), true)) {
            $value = $this->doLiteralBlock($value,$indent);
        }
        if (trim ($value) != $value) {
            $value = $this->doLiteralBlock($value,$indent);
        }
        if (is_bool($value)) {
            $value = ($value) ? "true" : "false";
        }
        if ($value === null) {
            $value = 'null';
        }
        if ($value === "'" . self::REMPTY . "'") {
            $value = null;
        }

        $spaces = str_repeat(' ',$indent);

        //if (is_int($key) && $key - 1 == $previous_key && $first_key===0) {
        if (is_array($source_array) && array_keys($source_array) === range(0, count($source_array) - 1)) {
            // It's a sequence
            $string = $spaces.'- '.$value."\n";
        } else {
            //if ($first_key===0)  {
            //    throw new Exception('Keys are all screwy.  The first one was zero, now it\'s "'. $key .'"');
            //}
            // It's mapped
            if (strpos($key, ":") !== false || strpos($key, "#") !== false) {
                $key = '"' . $key . '"';
            }

            $string = rtrim ($spaces.$key.': '.$value)."\n";
        }

        return $string;
    }

    /**
     * Creates a literal block for dumping
     * @access private
     * @return string
     * @param $value
     * @param $indent int The value of the indent
     */
    private function doLiteralBlock($value, $indent)
    {
        if ($value === "\n") {
            return '\n';
        }
        if (strpos($value, "\n") === false && strpos($value, "'") === false) {
            return sprintf ("'%s'", $value);
        }
        if (strpos($value, "\n") === false && strpos($value, '"') === false) {
            return sprintf ('"%s"', $value);
        }

        $exploded = explode("\n",$value);
        $newValue = '|';
        $indent  += $this->dumpIndent;
        $spaces   = str_repeat(' ',$indent);

        foreach ($exploded as $line) {
            $newValue .= "\n" . $spaces . $line;
        }

        return $newValue;
    }

    /**
     * Folds a string of text, if necessary
     * @access private
     * @return string
     * @param $value The string you wish to fold
     */
    private function doFolding($value, $indent)
    {
        // Don't do anything if wordwrap is set to 0
        if ($this->dumpWordWrap !== 0 && is_string ($value) && strlen($value) > $this->dumpWordWrap) {
            $indent += $this->dumpIndent;
            $indent  = str_repeat(' ',$indent);
            $wrapped = wordwrap($value,$this->dumpWordWrap,"\n$indent");
            $value   = ">\n".$indent.$wrapped;
        } else {
            if ($this->settingDumpForceQuotes && is_string ($value) && $value !== self::REMPTY) {
                $value = '"' . $value . '"';
            }
        }

        return $value;
    }

    // LOADING FUNCTIONS

    private function loadWithSource($Source)
    {
        if (empty ($Source)) {
            return array();
        }
        if ($this->settingUseSyckIsPossible && function_exists ('syck_load')) {
            $array = syck_load (implode ('', $Source));

            return is_array($array) ? $array : array();
        }

        $this->path = array();
        $this->result = array();
        $cnt = count($Source);

        for ($i = 0; $i < $cnt; $i++) {
            $line = $Source[$i];

            $this->indent = strlen($line) - strlen(ltrim($line));
            $tempPath = $this->getParentPathByIndent($this->indent);
            $line = self::stripIndent($line, $this->indent);

            if (self::isComment($line)) {
                continue;
            }
            if (self::isEmpty($line)) {
                continue;
            }

            $this->path = $tempPath;
            $literalBlockStyle = self::startsLiteralBlock($line);

            if ($literalBlockStyle) {
                $line = rtrim ($line, $literalBlockStyle . " \n");
                $literalBlock = '';
                $line .= $this->LiteralPlaceHolder;
                $literal_block_indent = strlen($Source[$i+1]) - strlen(ltrim($Source[$i+1]));

                while (++$i < $cnt && $this->literalBlockContinues($Source[$i], $this->indent)) {
                    $literalBlock = $this->addLiteralLine(
                        $literalBlock, $Source[$i], $literalBlockStyle, $literal_block_indent
                    );
                }

                $i--;
            }

            while (++$i < $cnt && self::greedilyNeedNextLine($line)) {
                $line = rtrim($line, " \n\t\r") . ' ' . ltrim($Source[$i], " \t");
            }

            $i--;

            if (strpos($line, '#')) {
                if (strpos($line, '"') === false && strpos($line, "'") === false) {
                    $line = preg_replace('/\s+#(.+)$/','',$line);
                }
            }

            $lineArray = $this->parseLine($line);

            if ($literalBlockStyle) {
                $lineArray = $this->revertLiteralPlaceHolder ($lineArray, $literalBlock);
            }

            $this->addArray($lineArray, $this->indent);

            foreach ($this->delayedPath as $indent => $delayedPath) {
                $this->path[$indent] = $delayedPath;
            }

            $this->delayedPath = array();

        }

        return $this->result;
    }

    private function loadFromFile($file)
    {
        if (!file_exists($file)) {
            throw new \Exception("Error: yaml file does not exist: $file");
        }

        return file($file);
    }

    private function loadFromString ($input)
    {
        $lines = explode("\n",$input);

        foreach ($lines as $k => $_) {
            $lines[$k] = rtrim ($_, "\r");
        }

        return $lines;
    }

    /**
     * Parses YAML code and returns an array for a node
     * @access private
     * @return array
     * @param string $line A line from the YAML file
     */
    private function parseLine($line)
    {
        if (!$line) {
            return array();
        }

        $line = trim($line);

        if (!$line) {
            return array();
        }

        $array = array();
        $group = $this->nodeContainsGroup($line);

        if ($group) {
            $this->addGroup($line, $group);
            $line = $this->stripGroup ($line, $group);
        }
        if ($this->startsMappedSequence($line)) {
            return $this->returnMappedSequence($line);
        }
        if ($this->startsMappedValue($line)) {
            return $this->returnMappedValue($line);
        }
        if ($this->isArrayElement($line)) {
            return $this->returnArrayElement($line);
        }
        if ($this->isPlainArray($line)) {
            return $this->returnPlainArray($line);
        }

        return $this->returnKeyValuePair($line);
    }

    /**
     * Finds the type of the passed value, returns the value as the new type.
     * @access private
     * @param string $value
     * @return mixed
     */
    private function toType($value)
    {
        if ($value === '') {
            return null;
        }

        $first_character = $value[0];
        $last_character = substr($value, -1, 1);
        $is_quoted = false;

        do {
            if (!$value) {
                break;
            }
            if ($first_character != '"' && $first_character != "'") {
                break;
            }
            if ($last_character != '"' && $last_character != "'") {
                break;
            }
            $is_quoted = true;
        } while (0);

        if ($is_quoted) {
            return strtr(substr($value, 1, -1), array('\\"' => '"', '\'\'' => '\'', '\\\'' => '\''));
        }

        if (strpos($value, ' #') !== false && !$is_quoted) {
            $value = preg_replace('/\s+#(.+)$/','',$value);
        }

        if (!$is_quoted) {
            $value = str_replace('\n', "\n", $value);
        }

        if ($first_character == '[' && $last_character == ']') {
            // Take out strings sequences and mappings
            $innerValue = trim(substr ($value, 1, -1));

            if ($innerValue === '') {
                return array();
            }

            $explode = $this->inlineEscape($innerValue);
            // Propagate value array
            $value  = array();

            foreach ($explode as $v) {
                $value[] = $this->toType($v);
            }

            return $value;
        }

        if (strpos($value,': ') !== false && $first_character != '{') {
            $array = explode(': ',$value);
            $key   = trim($array[0]);
            array_shift($array);
            $value = trim(implode(': ',$array));
            $value = $this->toType($value);

            return array($key => $value);
        }

        if ($first_character == '{' && $last_character == '}') {
            $innerValue = trim(substr($value, 1, -1));

            if ($innerValue === '') {
                return array();
            }

            // Inline Mapping
            // Take out strings sequences and mappings
            $explode = $this->inlineEscape($innerValue);
            // Propagate value array
            $array = array();

            foreach ($explode as $v) {
                $SubArr = $this->toType($v);
                if (empty($SubArr)) {
                    continue;
                }
                if (is_array ($SubArr)) {
                    $array[key($SubArr)] = $SubArr[key($SubArr)];
                    continue;
                }

                $array[] = $SubArr;
            }

            return $array;
        }

        if ($value == 'null' || $value == 'NULL' || $value == 'Null' || $value == '' || $value == '~') {
            return null;
        }

        if (is_numeric($value) && preg_match('/^(-|)[1-9]+[0-9]*$/', $value)) {
            $intvalue = (int)$value;
            if ($intvalue != PHP_INT_MAX) {
                $value = $intvalue;
            }

            return $value;
        }

        if (in_array($value, array('true', 'on', '+', 'yes', 'y', 'True', 'TRUE', 'On', 'ON', 'YES', 'Yes', 'Y'))) {
            return true;
        }

        if (in_array(strtolower($value), array('false', 'off', '-', 'no', 'n'))) {
            return false;
        }

        if (is_numeric($value)) {
            if ($value === '0') {
                return 0;
            }
            if (rtrim ($value, 0) === $value) {
                $value = (float)$value;
            }

            return $value;
        }

        return $value;
    }

    /**
     * Used in inlines to check for more inlines or quoted strings
     * @access private
     * @return array
     */
    private function inlineEscape($inline)
    {
        // There's gotta be a cleaner way to do this...
        // While pure sequences seem to be nesting just fine,
        // pure mappings and mappings with sequences inside can't go very
        // deep.  This needs to be fixed.

        $seqs = array();
        $maps = array();
        $saved_strings = array();

        // Check for strings
        $regex = '/(?:(")|(?:\'))((?(1)[^"]+|[^\']+))(?(1)"|\')/';

        if (preg_match_all($regex,$inline,$strings)) {
            $saved_strings = $strings[0];
            $inline  = preg_replace($regex, 'YAMLString', $inline);
        }

        unset($regex);

        $i = 0;
        do {
            // Check for sequences
            while (preg_match('/\[([^{}\[\]]+)\]/U',$inline,$matchseqs)) {
                $seqs[] = $matchseqs[0];
                $inline = preg_replace('/\[([^{}\[\]]+)\]/U', ('YAMLSeq' . (count($seqs) - 1) . 's'), $inline, 1);
            }

            // Check for mappings
            while (preg_match('/{([^\[\]{}]+)}/U',$inline,$matchmaps)) {
                $maps[] = $matchmaps[0];
                $inline = preg_replace('/{([^\[\]{}]+)}/U', ('YAMLMap' . (count($maps) - 1) . 's'), $inline, 1);
            }

            if ($i++ >= 10) {
                break;
            }
        } while (strpos ($inline, '[') !== false || strpos ($inline, '{') !== false);

        $explode = explode(', ', $inline);
        $stringi = 0;
        $i = 0;

        while (1) {
            // Re-add the sequences
            if (!empty($seqs)) {
                foreach ($explode as $key => $value) {
                    if (strpos($value,'YAMLSeq') !== false) {
                        foreach ($seqs as $seqk => $seq) {
                            $explode[$key] = str_replace(('YAMLSeq'.$seqk.'s'), $seq, $value);
                            $value = $explode[$key];
                        }
                    }
                }
            }

            // Re-add the mappings
            if (!empty($maps)) {
                foreach ($explode as $key => $value) {
                    if (strpos($value,'YAMLMap') !== false) {
                        foreach ($maps as $mapk => $map) {
                            $explode[$key] = str_replace(('YAMLMap'.$mapk.'s'), $map, $value);
                            $value = $explode[$key];
                        }
                    }
                }
            }

            // Re-add the strings
            if (!empty($saved_strings)) {
                foreach ($explode as $key => $value) {
                    while (strpos($value,'YAMLString') !== false) {
                        $explode[$key] = preg_replace('/YAMLString/', $saved_strings[$stringi], $value, 1);
                        unset($saved_strings[$stringi]);
                        ++$stringi;
                        $value = $explode[$key];
                    }
                }
            }

            $finished = true;

            foreach ($explode as $key => $value) {
                if (strpos($value,'YAMLSeq') !== false) {
                    $finished = false;
                    break;
                }
                if (strpos($value,'YAMLMap') !== false) {
                    $finished = false;
                    break;
                }
                if (strpos($value,'YAMLString') !== false) {
                    $finished = false;
                    break;
                }
            }

            if ($finished) {
                break;
            }

            $i++;

            if ($i > 10) {
                break; // Prevent infinite loops.
            }
        }

        return $explode;
    }

    private function literalBlockContinues ($line, $lineIndent)
    {
        if (!trim($line)) {
            return true;
        }
        if (strlen($line) - strlen(ltrim($line)) > $lineIndent) {
            return true;
        }

        return false;
    }

    private function referenceContentsByAlias ($alias)
    {
        do {
            if (!isset($this->SavedGroups[$alias])) {
                echo "Bad group name: $alias.";
                break;
            }

            $groupPath = $this->SavedGroups[$alias];
            $value = $this->result;

            foreach ($groupPath as $k) {
                $value = $value[$k];
            }
        } while (false);

        return $value;
    }

    private function addArrayInline ($array, $indent)
    {
        $CommonGroupPath = $this->path;

        if (empty ($array)) {
            return false;
        }

        foreach ($array as $k => $_) {
            $this->addArray(array($k => $_), $indent);
            $this->path = $CommonGroupPath;
        }

        return true;
    }

    private function addArray ($incoming_data, $incoming_indent)
    {
        if (count ($incoming_data) > 1) {
            return $this->addArrayInline ($incoming_data, $incoming_indent);
        }

        $key = key($incoming_data);
        $value = isset($incoming_data[$key]) ? $incoming_data[$key] : null;

        if ($key === '__!YAMLZero') {
            $key = '0';
        }

        if ($incoming_indent == 0 && !$this->containsGroupAlias && !$this->containsGroupAnchor) {
            // Shortcut for root-level values.
            if ($key || $key === '' || $key === '0') {
                $this->result[$key] = $value;
            } else {
                $this->result[] = $value;
                end($this->result);
                $key = key($this->result);
            }
            $this->path[$incoming_indent] = $key;

            return;
        }

        $history = array();
        // Unfolding inner array tree.
        $history[] = $_arr = $this->result;
        foreach ($this->path as $k) {
            $history[] = $_arr = $_arr[$k];
        }

        if ($this->containsGroupAlias) {
            $value = $this->referenceContentsByAlias($this->containsGroupAlias);
            $this->containsGroupAlias = false;
        }


        // Adding string or numeric key to the innermost level or $this->arr.
        if (is_string($key) && $key == '<<') {
            if (!is_array ($_arr)) {
                $_arr = array ();
            }

            $_arr = array_merge ($_arr, $value);
        } elseif ($key || $key === '' || $key === '0') {
            if (!is_array ($_arr)) {
                $_arr = array ($key=>$value);
            } else {
                $_arr[$key] = $value;
            }
        } else {
            if (!is_array ($_arr)) {
                $_arr = array ($value);
                $key = 0;
            } else {
                $_arr[] = $value;
                end($_arr);
                $key = key($_arr);
            }
        }

        $reverse_path = array_reverse($this->path);
        $reverse_history = array_reverse ($history);
        $reverse_history[0] = $_arr;
        $cnt = count($reverse_history) - 1;

        for ($i = 0; $i < $cnt; $i++) {
            $reverse_history[$i+1][$reverse_path[$i]] = $reverse_history[$i];
        }
        $this->result = $reverse_history[$cnt];

        $this->path[$incoming_indent] = $key;

        if ($this->containsGroupAnchor) {
            $this->SavedGroups[$this->containsGroupAnchor] = $this->path;
            if (is_array ($value)) {
                $k = key ($value);
                if (!is_int ($k)) {
                    $this->SavedGroups[$this->containsGroupAnchor][$incoming_indent + 2] = $k;
                }
            }

            $this->containsGroupAnchor = false;
        }
    }

    private static function startsLiteralBlock ($line)
    {
        $lastChar = substr (trim($line), -1);

        if ($lastChar != '>' && $lastChar != '|') {
            return false;
        }
        if ($lastChar == '|') {
            return $lastChar;
        }
        // HTML tags should not be counted as literal blocks.
        if (preg_match ('#<.*?>$#', $line)) {
            return false;
        }

        return $lastChar;
    }

    private static function greedilyNeedNextLine($line)
    {
        $line = trim ($line);

        if (!strlen($line)) {
            return false;
        }
        if (substr ($line, -1, 1) == ']') {
            return false;
        }
        if ($line[0] == '[') {
            return true;
        }
        if (preg_match ('#^[^:]+?:\s*\[#', $line)) {
            return true;
        }

        return false;
    }

    private function addLiteralLine ($literalBlock, $line, $literalBlockStyle, $indent = -1)
    {
        $line = self::stripIndent($line, $indent);

        if ($literalBlockStyle !== '|') {
            $line = self::stripIndent($line);
        }

        $line = rtrim ($line, "\r\n\t ") . "\n";

        if ($literalBlockStyle == '|') {
            return $literalBlock . $line;
        }
        if (strlen($line) == 0) {
            return rtrim($literalBlock, ' ') . "\n";
        }
        if ($line == "\n" && $literalBlockStyle == '>') {
            return rtrim($literalBlock, " \t") . "\n";
        }
        if ($line != "\n") {
            $line = trim($line, "\r\n ") . " ";
        }

        return $literalBlock . $line;
    }

    public function revertLiteralPlaceHolder ($lineArray, $literalBlock)
    {
        foreach ($lineArray as $k => $_) {
            if (is_array($_)) {
                $lineArray[$k] = $this->revertLiteralPlaceHolder ($_, $literalBlock);
            } elseif (substr($_, -1 * strlen($this->LiteralPlaceHolder)) == $this->LiteralPlaceHolder) {
                $lineArray[$k] = rtrim($literalBlock, " \r\n");
            }
        }

        return $lineArray;
    }

    private static function stripIndent ($line, $indent = -1)
    {
        if ($indent == -1) {
            $indent = strlen($line) - strlen(ltrim($line));
        }

        return substr ($line, $indent);
    }

    private function getParentPathByIndent ($indent)
    {
        if ($indent == 0) {
            return array();
        }
        $linePath = $this->path;

        do {
            end($linePath);
            $lastIndentInParentPath = key($linePath);

            if ($indent <= $lastIndentInParentPath) {
                array_pop($linePath);
            }
        } while ($indent <= $lastIndentInParentPath);

        return $linePath;
    }


    private function clearBiggerPathValues ($indent)
    {
        if ($indent == 0) {
            $this->path = array();
        }
        if (empty($this->path)) {
            return true;
        }

        foreach ($this->path as $k => $_) {
            if ($k > $indent) {
                unset($this->path[$k]);
            }
        }

        return true;
    }


    private static function isComment ($line)
    {
        if (!$line) {
            return false;
        }
        if ($line[0] == '#') {
            return true;
        }
        if (trim($line, " \r\n\t") == '---') {
            return true;
        }

        return false;
    }

    private static function isEmpty ($line)
    {
        return (trim ($line) === '');
    }


    private function isArrayElement ($line)
    {
        if (!$line) {
            return false;
        }
        if ($line[0] != '-') {
            return false;
        }
        if (strlen ($line) > 3) {
            if (substr($line,0,3) == '---') {
                return false;
            }
        }

        return true;
    }

    private function isHashElement ($line)
    {
        return strpos($line, ':');
    }

    private function isLiteral ($line)
    {
        if ($this->isArrayElement($line)) {
            return false;
        }
        if ($this->isHashElement($line)) {
            return false;
        }

        return true;
    }


    private static function unquote ($value)
    {
        if (!$value) {
            return $value;
        }
        if (!is_string($value)) {
            return $value;
        }
        if ($value[0] == '\'') {
            return trim ($value, '\'');
        }
        if ($value[0] == '"') {
            return trim ($value, '"');
        }

        return $value;
    }

    private function startsMappedSequence ($line)
    {
        return ($line[0] == '-' && substr ($line, -1, 1) == ':');
    }

    private function returnMappedSequence ($line)
    {
        $array = array();
        $key         = self::unquote(trim(substr($line,1,-1)));
        $array[$key] = array();
        $this->delayedPath = array(strpos ($line, $key) + $this->indent => $key);

        return array($array);
    }

    private function returnMappedValue ($line)
    {
        $array = array();
        $key         = self::unquote (trim(substr($line,0,-1)));
        $array[$key] = '';
        return $array;
    }

    private function startsMappedValue ($line)
    {
        return (substr ($line, -1, 1) == ':');
    }

    private function isPlainArray ($line)
    {
        return ($line[0] == '[' && substr ($line, -1, 1) == ']');
    }

    private function returnPlainArray ($line)
    {
        return $this->toType($line);
    }

    private function returnKeyValuePair ($line)
    {
        $array = array();
        $key = '';

        if (strpos ($line, ':')) {
            // It's a key/value pair most likely
            // If the key is in double quotes pull it out
            if (($line[0] == '"' || $line[0] == "'") && preg_match('/^(["\'](.*)["\'](\s)*:)/',$line,$matches)) {
                $value = trim(str_replace($matches[1],'',$line));
                $key   = $matches[2];
            } else {
                // Do some guesswork as to the key and the value
                $explode = explode(':',$line);
                $key     = trim($explode[0]);
                array_shift($explode);
                $value   = trim(implode(':',$explode));
            }
            // Set the type of the value.  Int, string, etc
            $value = $this->toType($value);
            if ($key === '0') {
                $key = '__!YAMLZero';
            }
            $array[$key] = $value;
        } else {
            $array = array ($line);
        }

        return $array;
    }


    private function returnArrayElement ($line)
    {
        if (strlen($line) <= 1) {
            return array(array()); // Weird %)
        }

        $array = array();
        $value   = trim(substr($line,1));
        $value   = $this->toType($value);
        $array[] = $value;

        return $array;
    }


    private function nodeContainsGroup ($line)
    {
        $symbolsForReference = 'A-z0-9_\-';

        if (strpos($line, '&') === false && strpos($line, '*') === false) {
            return false; // Please die fast ;-)
        }
        if ($line[0] == '&' && preg_match('/^(&['.$symbolsForReference.']+)/', $line, $matches)) {
            return $matches[1];
        }
        if ($line[0] == '*' && preg_match('/^(\*['.$symbolsForReference.']+)/', $line, $matches)) {
            return $matches[1];
        }
        if (preg_match('/(&['.$symbolsForReference.']+)$/', $line, $matches)) {
            return $matches[1];
        }
        if (preg_match('/(\*['.$symbolsForReference.']+$)/', $line, $matches)) {
            return $matches[1];
        }
        if (preg_match ('#^\s*<<\s*:\s*(\*[^\s]+).*$#', $line, $matches)) {
            return $matches[1];
        }

        return false;
    }

    private function addGroup ($line, $group)
    {
        if ($group[0] == '&') {
            $this->containsGroupAnchor = substr($group, 1);
        }
        if ($group[0] == '*') {
            $this->containsGroupAlias = substr($group, 1);
        }
    }

    private function stripGroup ($line, $group)
    {
        $line = trim(str_replace($group, '', $line));

        return $line;
    }
}

function get_sites() {
	$yaml = new Yaml();
	$data = $yaml->load( '/srv/config/config.yml' );

	$sites = [ 'skipped' => [], 'provisioned' => [] ];

	foreach ( (array) $data['sites'] as $name => $site ) {
		if ( isset( $site['skip_provisioning'] ) && ( $site['skip_provisioning'] == true ) ) {
			$sites['skipped'][ $name ] = $site;
		} else {
			$sites['provisioned'][ $name ] = $site;
		}
	}

	return $sites;
}

function site_class( $site ) {
	if ( isset( $site['skip_provisioning'] ) && $site['skip_provisioning'] ) {
		echo 'site_skip_provision';
	} else {
		echo 'site_provision';
	}
}

function display_sites( array $sites ) : void {
	foreach ( $sites as $name => $site ) {
		?>
		<article class="box <?php site_class( $site ); ?>">
			<p class="vvv-site-links">
				<a class="vvv-site-link" href="http://<?php echo $site['hosts'][0]; ?>"><?php echo $site['hosts'][0]; ?></a>
				<code>/srv/www/<?php echo $name; ?></code>
			</p>
			<h4><strong><?php echo $name; ?><?php echo isset( $site['description'] ) ? ' : ' . $site['description'] : ''; ?></strong></h4>
		</article>
		<?php
	}
}

function display_exts() {
	$exts = [
		'xdebug'          => [
			'name'        => 'XDebug',
			'command'     => 'vagrant ssh -c "switch_php_debugmod xdebug"',
			'active'      => extension_loaded( 'xdebug' ),
		],
		'tideways_xhprof' => [
			'name'        => 'Tideways XHProf',
			'command'     => 'vagrant ssh -c "switch_php_debugmod tideways_xhprof"',
			'active'      => extension_loaded( 'tideways_xhprof' ),
		],
		'pcov'            => [
			'name'        => 'PCov',
			'command'     => 'vagrant ssh -c "switch_php_debugmod pcov"',
			'active'      => extension_loaded( 'pcov' ),
		],
		'none'            => [
			'name'        => 'None',
			'command'     => 'vagrant ssh -c "switch_php_debugmod none"',
			'active'      => false,
		],
	];

	if ( ! extension_loaded( 'xdebug' ) && ! extension_loaded( 'tideways_xhprof' ) && ! extension_loaded( 'pcov' ) ) {
		$exts['none']['active'] = true;
	}


	foreach ( $exts as $ext => $data ) {
		$class = $data['active'] ? 'chip-enabled' : 'chip-disabled';

		if ( 'none' !== $ext && ! file_exists( '/etc/php/' . PHP_MAJOR_VERSION . "." . PHP_MINOR_VERSION . '/mods-available/' . $ext . '.ini' ) ) {
			$data['command'] = '<a href="https://varyingvagrantvagrants.org/docs/en-US/references/tideways-xhgui/">View Tideways/XHProf docs</a>';
		}

		echo '<div class="spacedown"><div class="' . $class .'">' . $data['name'] . '</div><code>' . $data['command'] . '</code></div>';
	}
}
?>
<!DOCTYPE html>
<html lang="en'">
	<head>
		<title>vvv dashboard</title>
		<meta name="viewport" content="width=device-width, initial-scale=1.0">
		<style>
			html {
				background-color: #2b303b;
			}

			body {
				font-family: monospace;
				color: #c0c5ce;
				padding: 0.25em;
				font-size: 14px;
				line-height: 1.6em;
			}

			@media only screen and (min-width: 600px) {
				body {
					font-size: 16px;
					padding: 3em;
					max-width: 1920px;
					margin-left: auto;
					margin-right: auto;
				}
			}

			p:first-child {
				margin-top: 0;
			}

			p:last-child {
				margin-bottom: 0;
			}

			a {
				color: #ebcb8b;
				text-decoration: none;
				background: #ebcb8b11;
				border-radius: 4px;
				border-bottom: 1px solid #ebcb8baa;
			}

			a:hover {
				color: #d08770;
				background: #d0877011;
				border-bottom: 1px solid #d08770aa;
			}

			a:focus, a:active {
				color: #ff00ee;
				background: #ff00ee11;
				border-bottom: 1px solid #ff1fddaa;
				outline: 2px solid #ff00ee;
			}

			h2, h3, h4, h5 {
				margin-top: 0;
			}

			pre, code {
				background: #2b303b;
				border-radius: 4px;
				border: 1px solid #4f5b66;
			}

			code {
				color: #a3be8c;
				padding: .1em .2em;
			}

			pre {
				padding: 2em 1em;
				overflow-x: scroll;
			}

			strong {
				color: #eff1f5;
			}

			#vvv_provision_fail,
			#vvv_hosts_fail,
			.top-notice-box.box {
				background: #9b323d;
				font-weight: bold;
				border: 1px solid #ebcb8b;
			}

			#vvv_disk_space_fail {
			padding: 0 5px;
			}

			@media only screen and (min-width: 600px) {
				.grid,
				.grid50 {
					display: grid;
				}

				.grid {
					grid-template-columns: 70% 30%;
				}

				.grid50 {
					/*grid-auto-columns: 50% 50%;*/
					grid-template-columns: repeat(2, 1fr);
					grid-gap: 1em;
					grid-auto-flow: dense;
				}
			}

			.grid, .grid50 {
				margin-bottom: 1em;
			}

			.grid50 .box {
				margin-bottom: 0;
			}

			.box {
				border-radius:4px;
				padding: 1.5em;
				background: #343d46;
				box-sizing: border-box;
				margin-bottom:1em;
			}

			.left-column {
				margin-right: 1em;
			}

			.site_item {
				margin: 0 -2em;
				padding: 1em 2em;
				border-top: 1px solid #2b303b;
			}

			.site_skip_provision {
				opacity: .4;
				border: 1px solid #343d46;
			}

			input,
			.button {
				padding: .5em 1em;
				border: 1px solid #ebcb8b;
				border-radius: 4px;
				background: #343d46;
				color: #ebcb8b;
				font-family: monospace;
				font-size: 16px;
				margin-bottom: 1em;
			}

			button:focus,
			button:active,
			.button:focus,
			.button:active,
			input:focus,
			input:active {
				color: #ff00ee;
				background: #ff00ee11;
				border-color: #ff1fdd;
				outline: none;
			}

			input[type="submit"]:hover {
				cursor: pointer;
			}

			.button {
				display: inline-block;
				width: 150px;
				margin-right: 15px;
			}

			input:hover,
			.button:hover {
				border-color: #d08770;
				color: #d08770;
				text-decoration: none;
				outline: none;
			}

			.warning {
				font-style: italic;

				background: #b54a55;
				color: white;

				padding: 1.5em;
				margin-left: -1.5em;
				margin-right: -1.5em;
				margin-bottom: -1.5em;
			}

			.warning .button {
				background: #913b44;
				color: white;
			}

			.warning .button:hover {
				color: #d08770;
			}


			.right-column > .box,
			.alt-box {
				background: #272b35;
				border: 1px solid #343d46;
			}

			.right-column > .box input,
			.right-column >.box .button,
			.alt-box input,
			.alt-box .button {
				background: ##e8c989;
			}

			.vvv-site-link {
				display: inline-block;
				margin-right: 1em;
			}

			.site-header-meta {
				float: right;
			}

			table {
				border-radius: 4px;
				width: 100%;
				text-align: left;
			}
			th {
				border-bottom: 1px solid #ebcb8b
			}

			th, td {
				padding: 10px 30px 10px 0px;
			}

			th:last-child,
			td:last-child {
				padding-right: 0;
			}

			.chips {
				list-style: none;
				margin-bottom: 1.5em;
				padding: 0;
			}

			.chip {
				display: inline-block;
				font-size: 75%;
				padding: 0.1em 0.75em;
			}

			.chip.default,
			.chip.active {
				border-color: white;
				color: white;
				font-weight: bold;
			}

			.chip-enabled,
			.chip-disabled {
				color: #f38080;
				font-weight: bold;
				padding-bottom: 0.3em;
			}

			.chip-enabled {
				color: green;
			}

			.chip + .chip {
				margin-right: 0.5em;
			}

			.spacedown {
				padding-bottom: 1em;
			}
		</style>
	</head>
	<body>
		<div class="grid">
			<main class="column left-column">
				<div class="vvv-sites">
					<?php $sites = get_sites(); ?>
					<?php display_sites( $sites['provisioned'] ); ?>
					<?php display_sites( $sites['skipped'] ); ?>
				</div>
			</main>
			<aside class="column right-column">
				<div class="box">
					<nav class="tools">
						<a class="button" href="//vvv.test/">vvv.test</a>
						<a class="button tool-memcached-admin" href="//vvv.test/memcached-admin/">memcachedAdmin</a>
						<a class="button tool-opcachestatus" href="//vvv.test/opcache-status/opcache.php">Opcache Status</a>
						<a class="button tool-opcache-gui" href="//vvv.test/opcache-gui/">Opcache Gui</a>
						<a class="button tool-mailhog" href="http://vvv.test:8025">MailHog</a>
						<a class="button tool-xhgui" href="//xhgui.vvv.test/">XHGui Profiler</a>
						<a class="button tool-webgrind" href="//vvv.test/webgrind/">Webgrind</a>
						<a class="button tool-xdebuginfo" href="//vvv.test/xdebuginfo/">Xdebug Info</a>
						<a class="button tool-phpinfo" href="//vvv.test/phpinfo/">PHP Info</a>
						<a class="button tool-phpstatus" href="php-status?html&full">PHP Status</a>
					</nav>
				</div>
				<div class="box alt-box">
					<h3>PHP Debugging Extensions</h3>
					<?php display_exts(); ?>

				</div>
			</aside>
		</div>
	</body>
</html>
