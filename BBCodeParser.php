<?php

/**
 * BBCode parser class
 * @author Tom Rijnbeek
 * @version 1.0
 *
 * @todo When Dominating12 moves to PHP 5.3, replace the create_function calls with proper anonymous functions.
 * @todo Smiley parsing.
 */
class BBCodeParser
{
    const BREAKTAG = '<br />';
    const ENCODING = 'UTF8';

    /**
     * Every BB-code is modelled as an entry in this array. Every array can contain the following keys:
     *
     *  tag: the lowercase name of the tag.
     *
     *  type: one of...
     *      (none): [tag]parsed[/tag]
     *      unparsed_content: [tag]unparsed[/tag]
     *      unparsed_equals: [tag=unparsed]parsed[/tag]
     *      unparsed_equals_content: [tag=unparsed]unparsed[/tag]
     *      parsed_equals: [tag=parsed]parsed[/tag]
     *      closed: [tag]; [tag/]; [tag /]
     *  
     *  content: only for unparsed_content, unparsed_equals_content and closed;
     *      the html that should replace the tag; $1 is replaced by the content
     *      of the tag, $2 contains the unparsed parameter.
     *
     *  before: only when content is not used; the html that should be inserted
     *      before the content; $1 is replaced by the parameter.
     *
     *  after: similar to before in every way, except for it is inserted after
     *      the content.
     *
     *  validate: a function that can validate all unparsed content and params;
     *      receives a reference to a parameter or array of parameters (only
     *      unparsed_equals_content) depending on the type.
     *
     *  trim: if set to 'inside', whitespace after the opening tag will be
     *      cleared; if set to 'outside', the whitespace after the closing
     *      tag will be trimmed; 'both' will trim both.
     *
     *  parameters: an array of additional parameters that can be added; the
     *      key should be the name of the parameter, the value should be
     *      another array that can have additional properties for the parameter
     *          match: a regex that the parameters have to match
     *          optional: if set to anything, the parameter is not required
     *      the parameters can be accessed in the before, after and content
     *      by {param} where param is the name of the parameter.
     * 
     * @var array
     */
    private static $bbCodes;

    /**
     * Gets the array of BB-codes modelled as entry in an array.
     * @see bbCodes
     * @return array
     */
    private static function getBBcodes()
    {
        if (!isset(self::$bbCodes))
            self::$bbCodes = array(
                array(
                    'tag' => 'b',
                    'before' => '<strong>',
                    'after' => '</strong>'
                ),

                array(
                    'tag' => 'i',
                    'before' => '<em>',
                    'after' => '</em>'
                ),

                array(
                    'tag' => 'img',
                    'type' => 'unparsed_content',
                    'content' => '<img src="$1" alt="" />',
                    'validate' => create_function('&$param', '
                        $param = BBCodeParser::validateURL($param);
                        return true;')
                ),

                array(
                    'tag' => 'li',
                    'before' => '<li>',
                    'after' => '</li>',
                    'trim' => 'both'
                ),

                array(
                    'tag' => 'list',
                    'before' => '<ul class="normal">',
                    'after' => '</ul>',
                    'trim' => 'inside'
                ),

                array(
                    'tag' => 'list',
                    'parameters' => array(
                        'type' => array('match' => '(disc|circle|square|decimal|decimal-leading-zero|lower-roman|upper-roman|lower-greek|lower-latin|upper-latin|armenian|georgian|lower-alpha|upper-alpha|none)')
                    ),
                    'before' => '<ul class="normal" style="list-style-type: {type};">',
                    'after' => '</ul>',
                    'trim' => 'inside'
                ),

                array(
                    'tag' => 'quote',
                    'before' => '<div class="quote-left"><div class="quote-right"><div class="quote-content">',
                    'after' => '</div></div></div>'
                ),

                array(
                    'tag' => 'quote',
                    'type' => 'unparsed_equals',
                    'before' => '<div class="quote-left"><div class="quote-right"><div class="quote-from">$1</div><div class="quote-content">',
                    'after' => '</div></div></div>'
                ),

                array(
                    'tag' => 's',
                    'before' => '<del>',
                    'after' => '</del>'
                ),

                array(
                    'tag' => 'size',
                    'type' => 'unparsed_equals',
                    'before' => '<span style="font-size: $1;">',
                    'after' => '</span>',
                    'validate' => create_function('&$param', '
                        if (is_numeric($param))
                            $param = $param . \'px\';
                        return true;')
                ),

                array(
                    'tag' => 'spoiler',
                    'before' => '<div class="spoilerContainer"><div class="spoilerHeader">+ Show Spoiler +</div><div class="spoilerContent" style="display:none">',
                    'after' => '</div></div>'
                ),

                array(
                    'tag' => 'u',
                    'before' => '<u>',
                    'after' => '</u>'
                ),

                array(
                    'tag' => 'url',
                    'type' => 'unparsed_content',
                    'content' => '<a href="$1" target="_blank">$1</a>',
                    'validate' => create_function('&$param', '
                        $param = BBCodeParser::validateURL($param);
                        return true;')
                ),

                array(
                    'tag' => 'url',
                    'type' => 'unparsed_equals',
                    'before' => '<a href="$1" target="_blank">',
                    'after' => '</a>',
                    'validate' => create_function('&$param', '
                        $param = BBCodeParser::validateURL($param);
                        return true;')
                ),

                array(
                    'tag' => 'youtube',
                    'type' => 'unparsed_content',
                    'content' => '<iframe width="560" height="315" src="//www.youtube.com/embed/$1" frameborder="0" allowfullscreen></iframe>',
                    'validate' => create_function('&$param', '
                        $param = BBCodeParser::extractYoutubeId($param);
                        return true;')
                ),
            );

        return self::$bbCodes;
    }

    /**
     * An array of itemcodes that can be used instead of [li]-tags.
     * @var array
     */
    private static $itemCodes;

    /**
     * Gets the array of itemcodes.
     * @see itemCodes
     * @return array
     */
    private static function getItemCodes()
    {
        if (!isset(self::$itemCodes))
            self::$itemCodes = array(
                '*' => 'disc',
                '@' => 'disc',
                '+' => 'square',
                'x' => 'square',
                '#' => 'square',
                'o' => 'circle',
                'O' => 'circle',
                '0' => 'circle',
            );

        return self::$itemCodes;
    }

    /**
     * An array of tags in which url's are not automatically turned into links.
     * @var array
     */
    private static $noAutolinkTags;

    /**
     * Gets the array of tags in which url's are not automatically turned into links.
     * @return [type] [description]
     */
    private static function getNoAutolinkTags()
    {
        if (!isset(self::$noAutolinkTags))
            self::$noAutolinkTags = array(
                'url',
            );

        return self::$noAutolinkTags;
    }

    /**
     * A dictionary from the first letters of all tags to the codes.
     * @var array
     */
    private static $codeDictionary;

    /**
     * Creates a dictionary from the first letters of all tags to the codes.
     * @return array
     */
    private static function getCodeDictionary()
    {
        if (!isset(self::$codeDictionary))
        {
            self::$codeDictionary = array();
            $bbCodes = self::getBBcodes();
            $itemCodes = self::getItemCodes();

            foreach ($bbCodes as $code)
                self::$codeDictionary[substr($code['tag'], 0, 1)][] = $code;

            // Make sure we don't skip the itemcodes
            foreach ($itemCodes as $c => $dummy)
                if (!isset(self::$codeDictionary[$c]))
                    self::$codeDictionary[$c] = array();
        }

        return self::$codeDictionary;
    }

    /**
     * Parses a string from BB-code to html.
     * @param  string $string
     * @return string
     * @todo Add some raw code parsing between pos and last_pos
     */
    public function parse($string)
    {
        // Initialisation
        $codeDictionary = self::getCodeDictionary();
        $itemCodes = self::getItemCodes();

        $tagStack = array();
        $pos = -1;

        // Whitespaces first
        $string = strtr($string, array("\n" => self::BREAKTAG));

        while ($pos !== false)
        {
            // Save the previous position.
            $prevPos = isset($prevPos) ? max($pos, $prevPos) : $pos; 

            // Look for next tag candidate.
            $pos = strpos($string, '[', $pos + 1);

            // Make sure we also parse the part after the last tag.
            if ($pos === false && $prevPos > $pos)
                $pos = strlen($string) + 1;

            // There is no need in processing single characters.
            if ($prevPos < $pos - 1)
            {
                // Negative positions are bad...
                $prevPos = max(0, $prevPos);

                // Get the section we want to process.
                $raw = substr($string, $prevPos, $pos - $prevPos);

                /**
                 * Autolinking
                 */
                // Check whether autolink is disabled currently.
                $autolink = true;
                $noAutolinkTags = self::getNoAutolinkTags();
                foreach ($tagStack as $tag)
                    if (in_array($tag['tag'], $noAutolinkTags))
                        $autolink = false;

                // Make sure we are not doing an existing section
                if (!isset($autolinkedUntil))
                    $autolinkedUntil = 0;
                if ($pos < $autolinkedUntil)
                    $autolink = false;
                $autolinkedUntil = $pos;

                if ($autolink)
                    $this->autolinkSection(&$raw);

                /**
                 * Processing
                 */
                // Only process if we actually changed something
                if ($raw != substr($string, $prevPos, $pos - $prevPos))
                {
                    $string = substr($string, 0, $prevPos) . $raw . substr($string, $pos);

                    // We might have added or remove tags, so we want to make sure we don't skip any.
                    $oldPos = $prevPos + strlen($raw);
                    $pos = strpos($string, '[', $prevPos);
                    if ($pos === false)
                        $pos = $oldPos;             // Just take the old one.
                    else
                        $pos = min($pos, $oldPos);  // Take the one that comes first. We will get back to the other later.
                }
            }

            // Break if we finished the end of the string.
            if ($pos >= strlen($string) - 1)
                break;

            // It is really easy to find the first letter of the tag.
            $tagChar = strtolower(substr($string, $pos + 1, 1));

            if ($tagChar == '/' && !empty($tagStack))   // Closing tag
            {
                $closePos = strpos($string, ']', $pos + 1);

                if ($closePos == $pos + 2)  // [/]
                    continue;

                $tag = strtolower(substr($string, $pos + 2, $closePos - ($pos + 2)));
                $toClose = array();
                do
                {
                    $code = array_pop($tagStack);
                    if (!$code) // Stack is empty, nothing to close
                        break;
                    $toClose[] = $code;
                } while ($code['tag'] != $tag);

                // Check if we did not find an opening tag for this closing tag.
                if (empty($tagStack) && (empty($code) || $code['tag'] != $tag))
                {
                    $tagStack = $toClose;
                    continue;
                }

                // Close all tags that were opened after the tag we are looking for.
                foreach ($toClose as $code)
                {
                    $string = substr($string, 0, $pos) . "\n" . $code['after'] . "\n" . substr($string, $closePos + 1);
                    $pos += strlen($code['after']) + 2;

                    // Trim whitespaces
                    if (isset($code['trim']) && $code['trim'] != 'inside' && preg_match('~(<br />|&nbsp;|\s)*~', substr($string, $pos), $matches) != 0)
                        $string = substr($string, 0, $pos) . substr($string, $pos + strlen($matches[0]));
                }

                if (!empty($toClose))
                {
                    $toClose = array();
                    $pos--;
                }

                continue;
            }

            if (!isset($codeDictionary[$tagChar]))      // No tag exists with this letter
                continue;

            $code = null;
            foreach ($codeDictionary[$tagChar] as $possible)
            {
                // Not a match?
                if (strtolower(substr($string, $pos + 1, strlen($possible['tag']))) != $possible['tag'])
                    continue;

                // Get the char directly after the tag-name.
                $nextChar = substr($string, $pos + 1 + strlen($possible['tag']), 1);

                if (!empty($possible['parameters']))
                {
                    if ($nextChar != ' ')
                        continue;
                }
                elseif (!isset($possible['type']))  // parsed_content, no parameters!
                {
                    if ($nextChar != ']')
                        continue;
                }
                elseif ($possible['type'] == 'unparsed_content')
                {
                    if ($nextChar != ']') continue;
                }
                elseif ($possible['type'] == 'unparsed_equals'
                    || $possible['type'] == 'unparsed_content'
                    || $possible['type'] == 'unparsed_equals_content')
                {
                    // One parameter: equals sign required.
                    if ($nextChar != '=') continue;
                }
                elseif ($possible['type'] == 'closed')
                {
                    if ($nextChar != ']' 
                        && substr($string, $pos + 1 + strlen($possible['tag']), 2) != '/]'
                        && substr($string, $pos + 1 + strlen($possible['tag']), 3) != ' /]') continue;
                }

                $paramPos = $pos + 1 + strlen($possible['tag']) + 1;

                // Parameter verification is a lot of work =/.
                if (!empty($possible['parameters']))
                {
                    $regex = array();
                    foreach ($possible['parameters'] as $p => $info)
                        $regex[] = '(\s+' . $p . '=' . (isset($info['match']) ? $info['match'] : '(.+?)') . ')' . (isset($info['optional']) ? '?' : '');

                    $valid = false;
                    $regexOrders = self::arrayPermutations($regex);
                    foreach ($regexOrders as $r)
                        if (preg_match('~^' . implode('', $r) . '\]~i', substr($string, $paramPos - 1), $matches) != 0)
                        {
                            $valid = true;
                            break;
                        }

                    // The parameters were not valid.
                    if (!$valid)
                        continue;

                    // Create an array of parameters and their values.
                    $params = array();
                    for ($i = 1, $n = count($matches); $i < $n; $i += 2)
                    {
                        $key = strtok(ltrim($matches[$i]), '=');
                        $params['{' . $key . '}'] = $matches[$i + 1];

                        // Get rid of the { and $ symbols, because that might go wrong later.
                        $params['{' . $key . '}'] = strtr($params['{' . $key . '}'], array('$' => '&#036;', '{' => '&#123;'));
                    }

                    // Make sure our params array is complete.
                    foreach ($possible['parameters'] as $p => $info)
                        if (!isset($params['{' . $p . '}']))
                            $params['{' . $p . '}'] = '';

                    $code = $possible;

                    // Now put in the parameters.
                    if (isset($code['before']))
                        $code['before'] = strtr($code['before'], $params);
                    if (isset($code['after']))
                        $code['after'] = strtr($code['after'], $params);
                    if (isset($code['content']))
                        $code['content'] = strtr($code['content'], $params);

                    // Shift the parameter position because we finished processing the parameters.
                    $paramPos += strlen($matches[0]) - 1;
                }
                else
                    // Well, it seems we got it!
                    $code = $possible;

                break;
            }

            // Item codes are special cases. They have the form [$] with $ a character from the itemCodes list.
            if ($code === null && isset($itemCodes[$tagChar]) && substr($string, $pos + 2, 1) == ']')
            {
                $code = $itemCodes[$tagChar];

                $inside = empty($tagStack) ? null : $tagStack[count($tagStack) - 1];

                // If we are not in a list or list-item, something is wrong!
                if ($inside === null || ($inside['tag'] != 'li' && $inside['tag'] != 'list'))
                    continue;

                $html = '';

                // Close previous li if necessary
                if ($inside['tag'] == 'li')
                    $html .= '</li>';

                // Open a new tag
                array_push($tagStack, array(
                    'tag' => 'li',
                    'after' => '</li>',
                    'trim' => 'both',
                ));
                $html .= '<li' . ($code == '' ? '' : ' type="' . $code . '"') . '>';

                $string = substr($string, 0, $pos) . "\n" . $html . "\n" . substr($string, $pos + 3);
                $pos += strlen($html) - 1 + 2;
            }

            // False alarm
            if ($code === null)
                continue;

            //$paramPos = $pos + 1 + strlen($code['tag']) + 1;

            // Actual parsing
            if (!isset($code['type']))  // parsed_content
            {
                // NB: this doesn't check for closing tags!
                array_push($tagStack, $code);
                $string = substr($string, 0, $pos) . "\n" . $code['before'] . "\n" . substr($string, $paramPos);
                $pos += strlen($code['before']) - 1 + 2;
            }
            elseif($code['type'] == 'unparsed_content')
            {
                // Don't parse content, just skip it.
                // Find out where this block ends
                $closePos = stripos($string, '[/' . substr($string, $pos + 1, strlen($code['tag'])) . ']', $paramPos);

                // Ignore the tag if it is not closed
                if ($closePos === false)
                    continue;

                $data = substr($string, $paramPos, $closePos - $paramPos);

                if (isset($code['validate']))
                    if (!$code['validate'](&$data))
                        continue;

                $content = strtr($code['content'], array('$1' => $data));
                $string = substr($string, 0, $pos) . "\n" . $content . "\n" . substr($string, $closePos + 3 + strlen($code['tag']));

                $pos += strlen($content) - 1 + 2;
            }
            elseif($code['type'] == 'unparsed_equals' || $code['type'] == 'parsed_equals')
            {
                $paramClosePos = strpos($string, ']', $paramPos);
            
                // Ignore invalidly closed tags
                if ($paramClosePos === false)
                    continue;

                $param = substr($string, $paramPos, $paramClosePos - $paramPos);

                if (isset($code['validate']))
                    if (!$code['validate'](&$param))
                        continue;

                // Recursively parse parameter
                if ($code['type'] == 'parsed_equals')
                    $param = $this->parse($param);

                // Proactively fill in the after variable
                $code['after'] = strtr($code['after'], array('$1' => $param));
                $before = strtr($code['before'], array('$1' => $param));

                array_push($tagStack, $code);

                $string = substr($string, 0, $pos) . "\n" . $before . "\n" . substr($string, $paramClosePos + 1);

                $pos += strlen($before) - 1 + 2;
            }
            elseif ($code['type'] == 'unparsed_equals_content')
            {
                $paramClosePos = strpos($string, ']', $paramPos);
                // Ignore invalidly closed tags
                if ($paramClosePos === false)
                    continue;

                $closePos = stripos($string, '[/' . substr($string, $pos + 1, strlen($code['tag'])) . ']', $paramClosePos);
                // Ignore the tag if it is not closed
                if ($closePos === false)
                    continue;

                $data = array(
                    substr($string, $paramClosePos + 1, $closePos - ($paramClosePos + 1)),
                    substr($string, $paramPos, $paramClosePos - $paramPos)
                );

                if (isset($code['validate']))
                    if (!$code['validate'](&$data))
                        continue;

                $content = strtr($code['content'], array('$1' => $data[0], '$2' => $data[1]));
                $string = substr($string, 0, $pos) . "\n" . $content . "\n" . substr($string, $closePos + 3 + strlen($code['tag']));

                $pos += strlen($content) - 1 + 2;
            }
            elseif ($code['type'] == 'closed')
            {
                $closePos = strpos($string, ']', $pos);
                $string = substr($string, 0, $pos) . "\n" . $code['content'] . "\n" . substr($string, $closePos + 1);

                $pos += strlen($tag['content']) - 1 + 2;
            }

            // Trim whitespaces
            if (isset($code['trim']) && $code['trim'] != 'outside' && preg_match('~(<br />|&nbsp;|\s)*~', substr($string, $pos + 1), $matches) != 0)
                $string = substr($string, 0, $pos + 1) . substr($string, $pos + 1 + strlen($matches[0]));
        }

        // Close all remaining open tags
        while ($tag = array_pop($tagStack))
            $string .= "\n" . $tag['after'] . "\n";

        // NB: I get rid of the markers here. Note that these were introduced so adding smileys is easier later.
        $string = strtr($string, array("\n" => ''));

        if (substr($string, 0, 1) == ' ')
            $string = '&nbsp;' . substr($string, 1);

        // Cleanup whitespace
        $string = strtr($string, array('  ' => '&nbsp;', "\r" => '', "\n" => self::BREAKTAG, self::BREAKTAG. ' ' => self::BREAKTAG . '&nbsp;', '&#13;' => "\n"));

        return $string;
    }

    /**
     * Automatically turns url's in html links.
     * @param  string $raw The raw source section.
     * @return string      The autolinked section.
     * @todo Automatic e-mail linking
     */
    private function autolinkSection(&$raw)
    {
        // URL's
        if ((strpos($raw, '://') !== false || strpos($raw, 'www') !== false) && strpos($raw, '[url') === false)
        {
            // Switch out quotes really quick because they can cause problems.
            $raw = strtr($raw, array('&#039;' => '\'', '&nbsp;' => self::ENCODING == 'UTF8' ? "\xC2\xA0" : "\xA0", '&quot;' => '>">', '"' => '<"<', '&lt;' => '<lt<'));

            // The big regular expression stuff
            if (is_string($result = preg_replace(array(
                    '~(?<=[\s>\.(;\'"]|^)((?:http|https)://[\w\-_%@:|]+(?:\.[\w\-_%]+)*(?::\d+)?(?:/[\w\-_\~%\.@!,\?&;=#(){}+:\'\\\\]*)*[/\w\-_\~%@\?;=#}\\\\])~i',
                    '~(?<=[\s>(\'<]|^)(www(?:\.[\w\-_]+)+(?::\d+)?(?:/[\w\-_\~%\.@!,\?&;=#(){}+:\'\\\\]*)*[/\w\-_\~%@\?;=#}\\\\])~i'
                ), array(
                    '[url]$1[/url]',
                    '[url=http://$1]$1[/url]'
                ), $raw)))
            {
                $raw = $result;
            }

            // Get the quotes and such back.
            $raw = strtr($raw, array('\'' => '&#039;', self::ENCODING == 'UTF8' ? "\xC2\xA0" : "\xA0" => '&nbsp;', '>">' => '&quot;', '<"<' => '"', '<lt<' => '&lt;'));
        }
    }

    /**
     * -------
     * Helpers
     * -------
     */
    /**
     * Cleans a URL and makes sure it is a valid one.
     * @param  string $url
     * @return string
     */
    public static function validateURL($url)
    {
        $url = strtr($url, array('<br>' => ''));
        if (strpos($url, 'http://') !== 0 && strpos($url, 'https://') !== 0)
            $url = 'http://' . $url;
        return $url;
    }

    /**
     * Extracts the Youtube video id from a string.
     * @param  string $url A url to the video or the id itself.
     * @return string      Youtube video id.
     */
    public static function extractYoutubeId($url)
    {
        if (preg_match('/youtube\.com\/watch\?v=([^\&\?\/]+)/', $url, $id))
            return $id[1];
        else if (preg_match('/youtube\.com\/embed\/([^\&\?\/]+)/', $url, $id))
            return $id[1];
        else if (preg_match('/youtube\.com\/v\/([^\&\?\/]+)/', $url, $id))
            return $id[1];
        else if (preg_match('/youtu\.be\/([^\&\?\/]+)/', $url, $id))
            return $id[1];
        else
            return $url;
    }

    /**
     * Gives all permutations of an array.
     * @param  array $array The array to be permuted.
     * @return array        An array of all permutations of the elements from the input array.
     */
    public static function arrayPermutations($array)
    {
        $c = count($array);

        // Base case for recursive function.
        if ($c == 1)
            return array($array);

        $perms = array();

        for ($i = 0; $i < $c; $i++)
        {
            // Create all permutations of the array without one element.
            $p = self::arrayPermutations(array_splice($array, $i, 1));
            // Prepend the missing element to all permutations.
            foreach ($p as $q)
            {
                array_unshift($q, $array[$i]);
                $perms[] = $q;
            }
        }

        return $perms;
    }
}