<?php
/**
 * Shortcore, a small url shortener service
 *   (c) 2009 Florian Anderiasch, <fa at art dash core dot org>
 *   BSD-licenced
 */


// START DEFAULT CONFIG, do no change, use the extra .config.php
$cfg = array(
    'dbfile' => '/home/www/shortcore/db/shortcore.db',
    'table' => 'shortcore',
    'DEBUG' => false,
    'home' => 'http://example.org/',
    'tpl_body' => '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
<head>
<title>Shortcore</title>
<meta http-equiv="content-type" content="text/html; charset=ISO-8859-1" />
</head>
<body>
<div>
%s
</div>
  <p style="float: right;">
    <a href="http://validator.w3.org/check?uri=referer"><img
        src="http://www.w3.org/Icons/valid-xhtml10"
        alt="Valid XHTML 1.0 Strict" height="31" width="88" style="border:0;" /></a>
  </p>
</body>
</html>'
);
// END DEFAULT CONFIG

require './shortcore.config.php';

$sc = new Shortcore($cfg);

/**
 * Everything Shortcore
 * @package shortcore
 */
class Shortcore {
    private $cfg;
    private $db;
    private $DEBUG;
    private $version;

    /**
     * Constructor
     * @param array $cfg config values
     */
    function __construct($cfg) {
        $this->version = '0.2';
        $this->cfg     = $cfg;
        $this->DEBUG   = $cfg['DEBUG'];
        $this->db      = new PDO('sqlite://'.$this->cfg['dbfile']);
        $this->handle();
    }

    /**
     * Grabs a result from the database
     * @param string $id the desired id
     * @return mixed
     */
    function getResult($id) {
        $sql_select = sprintf('SELECT * FROM %s WHERE id="%s"', $this->cfg['table'], $id);
        $q = $this->db->query($sql_select);
        if ($q === false) {
            $result = false;
        } else {
            $result = $q->fetch(PDO::FETCH_ASSOC);
        }
        return $result;
    }

    /**
     * Redirects to the shortened url
     * @param string $id what to look up
     */
    function redirect($id) {
        $preview = false;
        if (substr($id, -1) == '_') {
            $preview = true;
            $id = substr($id, 0, -1);
        }
        $result = $this->getResult($id);
        if (is_null($result) || false === $result) {
            $this->page();
        } else {
            $counter = intval($result['counter']) + 1;
            $sql_update  = sprintf('UPDATE %s SET counter="%s" WHERE id="%s";', $this->cfg['table'], $counter, $id);
            $p = $this->db->query($sql_update);
                if ($this->DEBUG) var_dump($p);
                if ($this->DEBUG) var_dump($this->db->errorInfo());
            if ($preview) {
                $link1 = sprintf('<a href="%s_%s">%s_%s</a>', $this->cfg['home'], $id, $this->cfg['home'], $id);
                $link2 = sprintf('<a href="%s">%s</a>', $result['url'], $result['url']);
                $text = sprintf('The link you clicked on, <em>%s</em>, is a redirect to <strong>%s</strong>,<br />'.
                                ' was shortened on <em>%s</em> and has been clicked %s times.', 
                    $link1, $link2, date('d.m.Y H:i',$result['created']), $counter);
                echo sprintf($this->cfg['tpl_body'],$text);
            } else {
                $this->page($result['url']);
            }
        }
    }

    /**
     * Adds a new shortened url
     * @param mixed $id the desired id
     * @param string $url the target url
     * @param mixed $title an optional title
     */
    function add($id = null, $url, $title = null) {
        $time = time();
        if (is_null($id)) {
            $id = $this->randomId();
            $result = $this->getResult($id);
            while (false !== $result) {
                $id = $this->randomId();
                $result = $this->getResult($id);
            }
        } else {
            $result = $this->getResult($id);

            while (false !== $result) {
                $last = substr($id, -1);
                if (intval($last) > 0 && intval($last) < 9) {
                    $id = substr( $id, 0, -1) . ($last+1);
                } else {
                    $id .= '2';
                }
                $result = $this->getResult($id);
            }

        }
        if (is_null($title)) {
            $title = 'untitled';
        }
        $sql_insert = sprintf('INSERT INTO %s VALUES("%s", "%s", "%s", 0, "%s");', 
                                $this->cfg['table'], $id, $url, $title, $time);
            if ($this->DEBUG) print_r($sql_insert);
        $this->db->query($sql_insert);
        $this->page($cfg['home'].'_'.$id.'_');
    }

    /**
     * Executes a redirect
     * @param mixed $arg where to go
     */
    function page($arg = null) {
        if (is_null($arg)) {
            $arg = $this->cfg['home'];
        }
        $this->_e('[page] '.$arg);
        header('HTTP/1.1 301 Moved Permanently');
        header('Location: '.$arg);
        exit;
    }

    /**
     * Handles front controller stuff
     */
    function handle() {
        $_id    = null;
        $_url   = null;
        $_title = null;
        $_help  = null;

        if(isset($_GET['help'])) {
            $_help = true;
        }

        if (isset($_GET['url']) && substr($_GET['url'],0,4) == 'http' && strlen($_GET['url']) > 10) {
            $_url = $_GET['url'];
        }
        if (isset($_GET['title']) && strlen(strip_tags($_GET['title'])) > 0 ) {
            $_title = strip_tags($_GET['title']);
        }
        if (isset($_GET['id'])) {
            $pat = '([a-z0-9-]{2,})i';
            if (preg_match($pat, $_GET['id'])) {
                $_id = $_GET['id'];
            }
        }

        // writing
        if (!is_null($_url)) {
            $this->_e('adding');
            $this->add($_id, $_url, $_title);
        // reading
        } else {
            // this is "/_<id>"
            if (!is_null($_id)) {
                $this->_e('redir');
                $this->redirect($_id);
            }
        }
        if (!is_null($_help)) {
            
            $bookmarklet = <<<BML
javascript:foo=prompt('id?');location.href='%shortcore.php?url='+encodeURIComponent(location.href)+'&amp;title='+encodeURIComponent(document.title)+'&amp;id='+foo
BML;
            $bookmarklet = sprintf($bookmarklet, $this->cfg['home']);
            $link = sprintf('<a href="%s">shorten</a>', $bookmarklet);

            $text = sprintf('<p>Powered by Shortcore v. %s</p>Bookmarklet: %s', $this->cfg['version'], $link);

            echo sprintf($this->cfg['tpl_body'],$text);
            exit;
        }
        $this->page();
    }

    /**
     * Generates a random id
     * @param int $len the desired length
     * @return string
     */
    function randomId($len = 4) {
        $choice = array();
        $a = ord('a');
        $z = ord('z');
        $A = ord('A');
        $Z = ord('Z');

        // ignore the hard to read chars
        $banned = array(ord('l'), ord('I'), ord('O'));

        for($i=$a;$i<=$z;$i++) {
            if (!in_array($i, $banned)) {
                $choice[] = chr($i);
            }
        }
        for($i=$A;$i<=$Z;$i++) {
            if (!in_array($i, $banned)) {
                $choice[] = chr($i);
            }
        }
        for($i=0;$i<=9;$i++) {
            $choice[] = $i;
        }
        $out = '';
        for ($i=0;$i<$len;$i++) {
            $rand = rand(0, count($choice)-1);
            $out .= $choice[$rand];
        }
        return $out;
    }

    /**
     * Debug wrapper to error_log()
     * @param mixed $arg what to show
     */
    function _e($arg) {
        error_log('(sho) '.$arg);
    }
}
?>
