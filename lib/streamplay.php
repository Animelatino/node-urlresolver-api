<?php
/* JavaScript PHP UnPacker 
 * @lscofield
 * GNU
 */

class JavaScriptUnpacker
{
    private $unbaser;
    private $payload;
    private $symtab;
    private $radix;
    private $count;

    function Detect($source)
    {
        $source = preg_replace("/ /", "", $source);
        preg_match("/eval\(function\(p,a,c,k,e,[r|d]?/", $source, $res);

        return (count($res) > 0);
    }

    function Unpack($source)
    {
        preg_match_all("/}\('(.*)', *(\d+), *(\d+), *'(.*?)'\.split\('\|'\)/", $source, $out);

        // Payload
        $this->payload = $out[1][0];
        // Words
        $this->symtab = preg_split("/\|/", $out[4][0]);
        // Radix
        $this->radix = (int) $out[2][0];
        // Words Count
        $this->count = (int) $out[3][0];

        if ($this->count != count($this->symtab)) return; // Malformed p.a.c.k.e.r symtab !

        //ToDo: Try catch
        $this->unbaser = new Unbaser($this->radix);

        $result = preg_replace_callback(
            '/\b\w+\b/',
            array($this, 'Lookup'),
            $this->payload
        );
        $result = str_replace('\\', '', $result);
        $this->ReplaceStrings($result);
        return $result;
    }

    function Lookup($matches)
    {
        $word = $matches[0];
        $ub = $this->symtab[$this->unbaser->Unbase($word)];
        $ret = !empty($ub) ? $ub : $word;
        return $ret;
    }

    function ReplaceStrings($source)
    {
        preg_match_all("/var *(_\w+)\=\[\"(.*?)\"\];/", $source, $out);
    }
}

class Unbaser
{
    private $base;
    private $dict;
    private $selector = 52;
    private $ALPHABET = array(
        52 => '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOP',
        54 => '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQR',
        62 => '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ',
        95 => ' !"#$%&\'()*+,-./0123456789:;<=>?@ABCDEFGHIJKLMNOPQRSTUVWXYZ[\]^_`abcdefghijklmnopqrstuvwxyz{|}~'
    );


    function __construct($base)
    {
        $this->base = $base;

        if ($this->base > 62) $this->selector = 95;
        else if ($this->base > 54) $this->selector = 62;
        else if ($this->base > 52) $this->selector = 54;
    }

    function Unbase($val)
    {
        if (2 <= $this->base && $this->base <= 36) {
            return intval($val, $this->base);
        } else {
            if (!isset($this->dict)) {

                $this->dict = array_flip(str_split($this->ALPHABET[$this->selector]));
            }
            $ret = 0;
            $valArray = array_reverse(str_split($val));

            for ($i = 0; $i < count($valArray); $i++) {
                $cipher = $valArray[$i];
                $ret += pow($this->base, $i) * $this->dict[$cipher];
            }
            return $ret;
            // UnbaseExtended($x, $base)
        }
    }
}

function streamplay($base64)
{
    $h = base64_decode($base64);
    if (strpos($h, "function getCalcReferrer") !== false) {
        $t1 = explode("function getCalcReferrer", $h);
        $h  = $t1[1];
    }
    //echo $h;
    //file_put_contents("stream.txt",$h);
    include("jsabc.php");
    $jsu = new JavaScriptUnpacker();
    $out = $jsu->Unpack($h);
    if (preg_match('/([http|https][\.\d\w\-\.\/\\\:\?\&\#\%\_]*(\.mp4))/', $out, $m)) {
        $link = $m[1];
        $t1   = explode("/", $link);
        $a145 = $t1[3];
        if (preg_match('/([\.\d\w\-\.\/\\\:\?\&\#\%\_]*(\.(srt|vtt)))/', $out, $xx)) {
            //src:"/srt/00686/ic19hoyeob1d_Italian.vtt"
            $srt = $xx[1];
            if (strpos("http", $srt) === false && $srt)
                $srt = "https://streamplay.to" . $srt;
        }
        $enc = $h;
        $dec = jsabc($enc);
        $dec = str_replace("Math.", "", $dec);
        $dec = preg_replace_callback(
            "/Math\[(.*?)\]/",
            function ($matches) {
                return preg_replace("/(\s|\"|\+)/", "", $matches[1]);;
            },
            $dec
        );
        $dec = preg_replace_callback(
            "/\[([a-dt\"\+]+)\]/",
            function ($matches) {
                return "." . preg_replace("/(\s|\"|\+)/", "", $matches[1]);;
            },
            $dec
        );
        $dec = str_replace("PI", "M_PI", $dec);
        $dec = preg_replace("/\/\*.*?\*\//", "", $dec);  // /* ceva */

        if (preg_match_all("/(\\$\(\s*\"\s*([a-zA-Z0-9_\.\:\_\-]+)\s*\"\)\.data\s*\(\s*\"(\w+)\")\s*\,([a-zA-Z0-9-\s\+\)\(\"]+)\)/", $dec, $m)) {
            for ($k = 0; $k < count($m[0]); $k++) {
                $orig = $m[0][$k];
                $rep = $m[1][$k];
                $func = $m[3][$k];
                $val = $m[4][$k];
                $func = str_replace(" ", "_", $func);
                $dec = str_replace($orig, "\$" . $func . "=" . $val, $dec) . ";";
                $dec = str_replace($rep . ")", "\$" . $func, $dec);
            }
        }
        if (preg_match("/((r\=)|(r\.splice)(.*?))\';eval/ms", $dec, $m)) {
            $rez = $m[1];
            $rez = preg_replace("/r\.splice\s*\(/", "array_splice(\$r,", $rez);
            $rez = preg_replace("/r\s*\[/", "\$r[", $rez);
            $rez = str_replace("1+\"1\"", "11", $rez);
            //$rez = str_replace("-(", "(", $rez);
            $rez = preg_replace("/r\s*\=/", "\$r=", $rez);
            $rez = str_replace("var op=\"sqrt\";", "", $rez);
            $rez = str_replace("op(", "sqrt(", $rez);
            $rez = str_replace("\$r[\"splice\"](", "array_splice(\$r,", $rez);
            $r = str_split(strrev($a145));
            eval($rez);
            $x    = implode($r);
            $link = str_replace($a145, $x, $link);
        } else {
            $link = "";
        }
    } else {
        $link = "";
    }

    return $link;
}
