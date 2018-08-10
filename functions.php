<?php
class ParallelCurl {

    public $max_requests;
    public $options;

    public $outstanding_requests;
    public $multi_handle;
    
    public function __construct($in_max_requests = 10, $in_options = array()) {
        $this->max_requests = $in_max_requests;
        $this->options = $in_options;
        
        $this->outstanding_requests = array();
        $this->multi_handle = curl_multi_init();
    }
    
    public function __destruct() {
    	$this->finishAllRequests();
    }

    public function setMaxRequests($in_max_requests) {
        $this->max_requests = $in_max_requests;
    }
    
    public function setOptions($in_options) {

        $this->options = $in_options;
    }

    public function startRequest($url, $callback, $user_data = array(), $post_fields=null) {

		if( $this->max_requests > 0 )
	        $this->waitForOutstandingRequestsToDropBelow($this->max_requests);
    
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt_array($ch, $this->options);
        curl_setopt($ch, CURLOPT_URL, $url);

        if (isset($post_fields)) {
            curl_setopt($ch, CURLOPT_POST, TRUE);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
        }
        
        curl_multi_add_handle($this->multi_handle, $ch);
        
        $ch_array_key = (int)$ch;

        $this->outstanding_requests[$ch_array_key] = array(
            'url' => $url,
            'callback' => $callback,
            'user_data' => $user_data,
        );
        
        $this->checkForCompletedRequests();
    }
    
    public function finishAllRequests() {
        $this->waitForOutstandingRequestsToDropBelow(1);
    }

    private function checkForCompletedRequests() {
	do {
		$mrc = curl_multi_exec($this->multi_handle, $active);
	} while ($mrc == CURLM_CALL_MULTI_PERFORM);

	while ($active && $mrc == CURLM_OK) {
		if (curl_multi_select($this->multi_handle) != -1) {
			do {
				$mrc = curl_multi_exec($this->multi_handle, $active);
			} while ($mrc == CURLM_CALL_MULTI_PERFORM);
		}
		else
			return;
	}
		
        while ($info = curl_multi_info_read($this->multi_handle)) {
        
            $ch = $info['handle'];
            $ch_array_key = (int)$ch;
            
            if (!isset($this->outstanding_requests[$ch_array_key])) {
                die("Error - handle wasn't found in requests: '$ch' in ".
                    print_r($this->outstanding_requests, true));
            }
            
            $request = $this->outstanding_requests[$ch_array_key];

            $url = $request['url'];
            $content = curl_multi_getcontent($ch);
            $callback = $request['callback'];
            $user_data = $request['user_data'];
            
            call_user_func($callback, $content, $url, $ch, $user_data);
            
            unset($this->outstanding_requests[$ch_array_key]);
            
            curl_multi_remove_handle($this->multi_handle, $ch);
        }
    
    }
    
    private function waitForOutstandingRequestsToDropBelow($max)
    {
        while (1) {
            $this->checkForCompletedRequests();
            if (count($this->outstanding_requests)<$max)
            	break;
            
            usleep(10000);
        }
    }

}


function url_to_absolute( $baseUrl, $relativeUrl )
{
	$r = split_url( $relativeUrl );
	if ( $r === FALSE )
		return FALSE;
	if ( !empty( $r['scheme'] ) )
	{
		if ( !empty( $r['path'] ) && $r['path'][0] == '/' )
			$r['path'] = url_remove_dot_segments( $r['path'] );
		return join_url( $r );
	}

	$b = split_url( $baseUrl );
	if ( $b === FALSE || empty( $b['scheme'] ) || empty( $b['host'] ) )
		return FALSE;
	$r['scheme'] = $b['scheme'];

	if ( isset( $r['host'] ) )
	{
		if ( !empty( $r['path'] ) )
			$r['path'] = url_remove_dot_segments( $r['path'] );
		return join_url( $r );
	}
	unset( $r['port'] );
	unset( $r['user'] );
	unset( $r['pass'] );

	$r['host'] = $b['host'];
	if ( isset( $b['port'] ) ) $r['port'] = $b['port'];
	if ( isset( $b['user'] ) ) $r['user'] = $b['user'];
	if ( isset( $b['pass'] ) ) $r['pass'] = $b['pass'];

	if ( empty( $r['path'] ) )
	{
		if ( !empty( $b['path'] ) )
			$r['path'] = $b['path'];
		if ( !isset( $r['query'] ) && isset( $b['query'] ) )
			$r['query'] = $b['query'];
		return join_url( $r );
	}

	if ( $r['path'][0] != '/' )
	{
		$base = mb_strrchr( $b['path'], '/', TRUE, 'UTF-8' );
		if ( $base === FALSE ) $base = '';
		$r['path'] = $base . '/' . $r['path'];
	}
	$r['path'] = url_remove_dot_segments( $r['path'] );
	return join_url( $r );
}

function url_remove_dot_segments( $path )
{
	$inSegs  = preg_split( '!/!u', $path );
	$outSegs = array( );
	foreach ( $inSegs as $seg )
	{
		if ( $seg == '' || $seg == '.')
			continue;
		if ( $seg == '..' )
			array_pop( $outSegs );
		else
			array_push( $outSegs, $seg );
	}
	$outPath = implode( '/', $outSegs );
	if ( $path[0] == '/' )
		$outPath = '/' . $outPath;
	if ( $outPath != '/' &&
		(mb_strlen($path)-1) == mb_strrpos( $path, '/', 'UTF-8' ) )
		$outPath .= '/';
	return $outPath;
}

function split_url( $url, $decode=FALSE)
{
	$xunressub     = 'a-zA-Z\d\-._~\!$&\'()*+,;=';
	$xpchar        = $xunressub . ':@% ';

	$xscheme        = '([a-zA-Z][a-zA-Z\d+-.]*)';

	$xuserinfo     = '((['  . $xunressub . '%]*)' .
	                 '(:([' . $xunressub . ':%]*))?)';

	$xipv4         = '(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})';

	$xipv6         = '(\[([a-fA-F\d.:]+)\])';

	$xhost_name    = '([a-zA-Z\d-.%]+)';

	$xhost         = '(' . $xhost_name . '|' . $xipv4 . '|' . $xipv6 . ')';
	$xport         = '(\d*)';
	$xauthority    = '((' . $xuserinfo . '@)?' . $xhost .
		         '?(:' . $xport . ')?)';

	$xslash_seg    = '(/[' . $xpchar . ']*)';
	$xpath_authabs = '((//' . $xauthority . ')((/[' . $xpchar . ']*)*))';
	$xpath_rel     = '([' . $xpchar . ']+' . $xslash_seg . '*)';
	$xpath_abs     = '(/(' . $xpath_rel . ')?)';
	$xapath        = '(' . $xpath_authabs . '|' . $xpath_abs .
			 '|' . $xpath_rel . ')';

	$xqueryfrag    = '([' . $xpchar . '/?' . ']*)';

	$xurl          = '^(' . $xscheme . ':)?' .  $xapath . '?' .
	                 '(\?' . $xqueryfrag . ')?(#' . $xqueryfrag . ')?$';


	if ( !preg_match( '!' . $xurl . '!', $url, $m ) )
		return FALSE;

	if ( !empty($m[2]) )		$parts['scheme']  = strtolower($m[2]);

	if ( !empty($m[7]) ) {
		if ( isset( $m[9] ) )	$parts['user']    = $m[9];
		else			$parts['user']    = '';
	}
	if ( !empty($m[10]) )		$parts['pass']    = $m[11];

	if ( !empty($m[13]) )		$h=$parts['host'] = $m[13];
	else if ( !empty($m[14]) )	$parts['host']    = $m[14];
	else if ( !empty($m[16]) )	$parts['host']    = $m[16];
	else if ( !empty( $m[5] ) )	$parts['host']    = '';
	if ( !empty($m[17]) )		$parts['port']    = $m[18];

	if ( !empty($m[19]) )		$parts['path']    = $m[19];
	else if ( !empty($m[21]) )	$parts['path']    = $m[21];
	else if ( !empty($m[25]) )	$parts['path']    = $m[25];

	if ( !empty($m[27]) )		$parts['query']   = $m[28];
	if ( !empty($m[29]) )		$parts['fragment']= $m[30];

	if ( !$decode )
		return $parts;
	if ( !empty($parts['user']) )
		$parts['user']     = rawurldecode( $parts['user'] );
	if ( !empty($parts['pass']) )
		$parts['pass']     = rawurldecode( $parts['pass'] );
	if ( !empty($parts['path']) )
		$parts['path']     = rawurldecode( $parts['path'] );
	if ( isset($h) )
		$parts['host']     = rawurldecode( $parts['host'] );
	if ( !empty($parts['query']) )
		$parts['query']    = rawurldecode( $parts['query'] );
	if ( !empty($parts['fragment']) )
		$parts['fragment'] = rawurldecode( $parts['fragment'] );
	return $parts;
}


function join_url( $parts, $encode=FALSE)
{
	if ( $encode )
	{
		if ( isset( $parts['user'] ) )
			$parts['user']     = rawurlencode( $parts['user'] );
		if ( isset( $parts['pass'] ) )
			$parts['pass']     = rawurlencode( $parts['pass'] );
		if ( isset( $parts['host'] ) &&
			!preg_match( '!^(\[[\da-f.:]+\]])|([\da-f.:]+)$!ui', $parts['host'] ) )
			$parts['host']     = rawurlencode( $parts['host'] );
		if ( !empty( $parts['path'] ) )
			$parts['path']     = preg_replace( '!%2F!ui', '/',
				rawurlencode( $parts['path'] ) );
		if ( isset( $parts['query'] ) )
			$parts['query']    = rawurlencode( $parts['query'] );
		if ( isset( $parts['fragment'] ) )
			$parts['fragment'] = rawurlencode( $parts['fragment'] );
	}

	$url = '';
	if ( !empty( $parts['scheme'] ) )
		$url .= $parts['scheme'] . ':';
	if ( isset( $parts['host'] ) )
	{
		$url .= '//';
		if ( isset( $parts['user'] ) )
		{
			$url .= $parts['user'];
			if ( isset( $parts['pass'] ) )
				$url .= ':' . $parts['pass'];
			$url .= '@';
		}
		if ( preg_match( '!^[\da-f]*:[\da-f.:]+$!ui', $parts['host'] ) )
			$url .= '[' . $parts['host'] . ']';
		else
			$url .= $parts['host'];
		if ( isset( $parts['port'] ) )
			$url .= ':' . $parts['port'];
		if ( !empty( $parts['path'] ) && $parts['path'][0] != '/' )
			$url .= '/';
	}
	if ( !empty( $parts['path'] ) )
		$url .= $parts['path'];
	if ( isset( $parts['query'] ) )
		$url .= '?' . $parts['query'];
	if ( isset( $parts['fragment'] ) )
		$url .= '#' . $parts['fragment'];
	return $url;
}

function encode_url($url) {
  $reserved = array(
    ":" => '!%3A!ui',
    "/" => '!%2F!ui',
    "?" => '!%3F!ui',
    "#" => '!%23!ui',
    "[" => '!%5B!ui',
    "]" => '!%5D!ui',
    "@" => '!%40!ui',
    "!" => '!%21!ui',
    "$" => '!%24!ui',
    "&" => '!%26!ui',
    "'" => '!%27!ui',
    "(" => '!%28!ui',
    ")" => '!%29!ui',
    "*" => '!%2A!ui',
    "+" => '!%2B!ui',
    "," => '!%2C!ui',
    ";" => '!%3B!ui',
    "=" => '!%3D!ui',
    "%" => '!%25!ui',
  );

  $url = rawurlencode($url);
  $url = preg_replace(array_values($reserved), array_keys($reserved), $url);
  return $url;
}

@ $db = mysqli_connect("localhost", "paarooi1_admin", "231375", "paarooi1_web");
$main_title = 'پارو | Paaroo';
$keyd = array();
function remote_filesize($url) {
    static $regex = '/^Content-Length: *+\K\d++$/im';
    if (!$fp = @fopen($url, 'rb')) {
        return false;
    }
    if (
        isset($http_response_header) &&
        preg_match($regex, implode("\n", $http_response_header), $matches)
    ) {
        return (int)$matches[0];
    }
	return strlen(stream_get_contents($fp));
}
function FileSizeConvert($bytes)
{
    $bytes = floatval($bytes);
        $arBytes = array(
            0 => array(
                "UNIT" => "TB",
                "VALUE" => pow(1024, 4)
            ),
            1 => array(
                "UNIT" => "GB",
                "VALUE" => pow(1024, 3)
            ),
            2 => array(
                "UNIT" => "MB",
                "VALUE" => pow(1024, 2)
            ),
            3 => array(
                "UNIT" => "KB",
                "VALUE" => 1024
            ),
            4 => array(
                "UNIT" => "B",
                "VALUE" => 1
            ),
        );

    foreach($arBytes as $arItem)
    {
        if($bytes >= $arItem["VALUE"])
        {
            $result = $bytes / $arItem["VALUE"];
            $result = strval(round($result, 2))." ".$arItem["UNIT"];
            break;
        }
    }
    return $result;
}
function type($url)
{
        $mime_types = array(
                ".aw"=>"application/applixware"
				,".atom"=>"application/atom+xml"
				,".atomcat"=>"application/atomcat+xml"
				,".atomsvc"=>"application/atomsvc+xml"
				,".ccxml"=>"application/ccxml+xml"
				,".cdmia"=>"application/cdmi-capability"
				,".cdmic"=>"application/cdmi-container"
				,".cdmid"=>"application/cdmi-domain"
				,".cdmio"=>"application/cdmi-object"
				,".cdmiq"=>"application/cdmi-queue"
				,".cu"=>"application/cu-seeme"
				,".davmount"=>"application/davmount+xml"
				,".dssc"=>"application/dssc+der"
				,".xdssc"=>"application/dssc+xml"
				,".es"=>"application/ecmascript"
				,".emma"=>"application/emma+xml"
				,".epub"=>"application/epub+zip"
				,".exi"=>"application/exi"
				,".pfr"=>"application/font-tdpfr"
				,".stk"=>"application/hyperstudio"
				,".ipfix"=>"application/ipfix"
				,".jar"=>"application/java-archive"
				,".ser"=>"application/java-serialized-object"
				,".class"=>"application/java-vm"
				,".js"=>"application/javascript"
				,".json"=>"application/json"
				,".hqx"=>"application/mac-binhex40"
				,".cpt"=>"application/mac-compactpro"
				,".mads"=>"application/mads+xml"
				,".mrc"=>"application/marc"
				,".mrcx"=>"application/marcxml+xml"
				,".ma"=>"application/mathematica"
				,".mathml"=>"application/mathml+xml"
				,".mbox"=>"application/mbox"
				,".mscml"=>"application/mediaservercontrol+xml"
				,".meta4"=>"application/metalink4+xml"
				,".mets"=>"application/mets+xml"
				,".mods"=>"application/mods+xml"
				,".m21"=>"application/mp21"
				,".doc"=>"application/msword"
				,".mxf"=>"application/mxf"
				,".bin"=>"application/octet-stream"
				,".oda"=>"application/oda"
				,".opf"=>"application/oebps-package+xml"
				,".ogx"=>"application/ogg"
				,".onetoc"=>"application/onenote"
				,".xer"=>"application/patch-ops-error+xml"
				,".pdf"=>"application/pdf"
				,".pgp"=>"application/pgp-signature"
				,".prf"=>"application/pics-rules"
				,".p10"=>"application/pkcs10"
				,".p7m"=>"application/pkcs7-mime"
				,".p7s"=>"application/pkcs7-signature"
				,".p8"=>"application/pkcs8"
				,".ac"=>"application/pkix-attr-cert"
				,".cer"=>"application/pkix-cert"
				,".crl"=>"application/pkix-crl"
				,".pkipath"=>"application/pkix-pkipath"
				,".pki"=>"application/pkixcmp"
				,".pls"=>"application/pls+xml"
				,".ai"=>"application/postscript"
				,".cww"=>"application/prs.cww"
				,".pskcxml"=>"application/pskc+xml"
				,".rdf"=>"application/rdf+xml"
				,".rif"=>"application/reginfo+xml"
				,".rnc"=>"application/relax-ng-compact-syntax"
				,".rl"=>"application/resource-lists+xml"
				,".rld"=>"application/resource-lists-diff+xml"
				,".rs"=>"application/rls-services+xml"
				,".rsd"=>"application/rsd+xml"
				,".rss"=>"application/rss+xml"
				,".rtf"=>"application/rtf"
				,".sbml"=>"application/sbml+xml"
				,".scq"=>"application/scvp-cv-request"
				,".scs"=>"application/scvp-cv-response"
				,".spq"=>"application/scvp-vp-request"
				,".spp"=>"application/scvp-vp-response"
				,".sdp"=>"application/sdp"
				,".setpay"=>"application/set-payment-initiation"
				,".setreg"=>"application/set-registration-initiation"
				,".shf"=>"application/shf+xml"
				,".smi"=>"application/smil+xml"
				,".rq"=>"application/sparql-query"
				,".srx"=>"application/sparql-results+xml"
				,".gram"=>"application/srgs"
				,".grxml"=>"application/srgs+xml"
				,".sru"=>"application/sru+xml"
				,".ssml"=>"application/ssml+xml"
				,".tei"=>"application/tei+xml"
				,".tfi"=>"application/thraud+xml"
				,".tsd"=>"application/timestamped-data"
				,".plb"=>"application/vnd.3gpp.pic-bw-large"
				,".psb"=>"application/vnd.3gpp.pic-bw-small"
				,".pvb"=>"application/vnd.3gpp.pic-bw-var"
				,".tcap"=>"application/vnd.3gpp2.tcap"
				,".pwn"=>"application/vnd.3m.post-it-notes"
				,".aso"=>"application/vnd.accpac.simply.aso"
				,".imp"=>"application/vnd.accpac.simply.imp"
				,".acu"=>"application/vnd.acucobol"
				,".atc"=>"application/vnd.acucorp"
				,".air"=>"application/vnd.adobe.air-application-installer-package+zip"
				,".fxp"=>"application/vnd.adobe.fxp"
				,".xdp"=>"application/vnd.adobe.xdp+xml"
				,".xfdf"=>"application/vnd.adobe.xfdf"
				,".ahead"=>"application/vnd.ahead.space"
				,".azf"=>"application/vnd.airzip.filesecure.azf"
				,".azs"=>"application/vnd.airzip.filesecure.azs"
				,".azw"=>"application/vnd.amazon.ebook"
				,".acc"=>"application/vnd.americandynamics.acc"
				,".ami"=>"application/vnd.amiga.ami"
				,".apk"=>"application/vnd.android.package-archive"
				,".cii"=>"application/vnd.anser-web-certificate-issue-initiation"
				,".fti"=>"application/vnd.anser-web-funds-transfer-initiation"
				,".atx"=>"application/vnd.antix.game-component"
				,".mpkg"=>"application/vnd.apple.installer+xml"
				,".m3u8"=>"application/vnd.apple.mpegurl"
				,".swi"=>"application/vnd.aristanetworks.swi"
				,".aep"=>"application/vnd.audiograph"
				,".mpm"=>"application/vnd.blueice.multipass"
				,".bmi"=>"application/vnd.bmi"
				,".rep"=>"application/vnd.businessobjects"
				,".cdxml"=>"application/vnd.chemdraw+xml"
				,".mmd"=>"application/vnd.chipnuts.karaoke-mmd"
				,".cdy"=>"application/vnd.cinderella"
				,".cla"=>"application/vnd.claymore"
				,".rp9"=>"application/vnd.cloanto.rp9"
				,".c4g"=>"application/vnd.clonk.c4group"
				,".c11amc"=>"application/vnd.cluetrust.cartomobile-config"
				,".c11amz"=>"application/vnd.cluetrust.cartomobile-config-pkg"
				,".csp"=>"application/vnd.commonspace"
				,".cdbcmsg"=>"application/vnd.contact.cmsg"
				,".cmc"=>"application/vnd.cosmocaller"
				,".clkx"=>"application/vnd.crick.clicker"
				,".clkk"=>"application/vnd.crick.clicker.keyboard"
				,".clkp"=>"application/vnd.crick.clicker.palette"
				,".clkt"=>"application/vnd.crick.clicker.template"
				,".clkw"=>"application/vnd.crick.clicker.wordbank"
				,".wbs"=>"application/vnd.criticaltools.wbs+xml"
				,".pml"=>"application/vnd.ctc-posml"
				,".ppd"=>"application/vnd.cups-ppd"
				,".car"=>"application/vnd.curl.car"
				,".pcurl"=>"application/vnd.curl.pcurl"
				,".rdz"=>"application/vnd.data-vision.rdz"
				,".fe_launch"=>"application/vnd.denovo.fcselayout-link"
				,".dna"=>"application/vnd.dna"
				,".mlp"=>"application/vnd.dolby.mlp"
				,".dpg"=>"application/vnd.dpgraph"
				,".dfac"=>"application/vnd.dreamfactory"
				,".ait"=>"application/vnd.dvb.ait"
				,".svc"=>"application/vnd.dvb.service"
				,".geo"=>"application/vnd.dynageo"
				,".mag"=>"application/vnd.ecowin.chart"
				,".nml"=>"application/vnd.enliven"
				,".esf"=>"application/vnd.epson.esf"
				,".msf"=>"application/vnd.epson.msf"
				,".qam"=>"application/vnd.epson.quickanime"
				,".slt"=>"application/vnd.epson.salt"
				,".ssf"=>"application/vnd.epson.ssf"
				,".es3"=>"application/vnd.eszigno3+xml"
				,".ez2"=>"application/vnd.ezpix-album"
				,".ez3"=>"application/vnd.ezpix-package"
				,".fdf"=>"application/vnd.fdf"
				,".seed"=>"application/vnd.fdsn.seed"
				,".gph"=>"application/vnd.flographit"
				,".ftc"=>"application/vnd.fluxtime.clip"
				,".fm"=>"application/vnd.framemaker"
				,".fnc"=>"application/vnd.frogans.fnc"
				,".ltf"=>"application/vnd.frogans.ltf"
				,".fsc"=>"application/vnd.fsc.weblaunch"
				,".oas"=>"application/vnd.fujitsu.oasys"
				,".oa2"=>"application/vnd.fujitsu.oasys2"
				,".oa3"=>"application/vnd.fujitsu.oasys3"
				,".fg5"=>"application/vnd.fujitsu.oasysgp"
				,".bh2"=>"application/vnd.fujitsu.oasysprs"
				,".ddd"=>"application/vnd.fujixerox.ddd"
				,".xdw"=>"application/vnd.fujixerox.docuworks"
				,".xbd"=>"application/vnd.fujixerox.docuworks.binder"
				,".fzs"=>"application/vnd.fuzzysheet"
				,".txd"=>"application/vnd.genomatix.tuxedo"
				,".ggb"=>"application/vnd.geogebra.file"
				,".ggt"=>"application/vnd.geogebra.tool"
				,".gex"=>"application/vnd.geometry-explorer"
				,".gxt"=>"application/vnd.geonext"
				,".g2w"=>"application/vnd.geoplan"
				,".g3w"=>"application/vnd.geospace"
				,".gmx"=>"application/vnd.gmx"
				,".kml"=>"application/vnd.google-earth.kml+xml"
				,".kmz"=>"application/vnd.google-earth.kmz"
				,".gqf"=>"application/vnd.grafeq"
				,".gac"=>"application/vnd.groove-account"
				,".ghf"=>"application/vnd.groove-help"
				,".gim"=>"application/vnd.groove-identity-message"
				,".grv"=>"application/vnd.groove-injector"
				,".gtm"=>"application/vnd.groove-tool-message"
				,".tpl"=>"application/vnd.groove-tool-template"
				,".vcg"=>"application/vnd.groove-vcard"
				,".hal"=>"application/vnd.hal+xml"
				,".zmm"=>"application/vnd.handheld-entertainment+xml"
				,".hbci"=>"application/vnd.hbci"
				,".les"=>"application/vnd.hhe.lesson-player"
				,".hpgl"=>"application/vnd.hp-hpgl"
				,".hpid"=>"application/vnd.hp-hpid"
				,".hps"=>"application/vnd.hp-hps"
				,".jlt"=>"application/vnd.hp-jlyt"
				,".pcl"=>"application/vnd.hp-pcl"
				,".pclxl"=>"application/vnd.hp-pclxl"
				,".sfd-hdstx"=>"application/vnd.hydrostatix.sof-data"
				,".x3d"=>"application/vnd.hzn-3d-crossword"
				,".mpy"=>"application/vnd.ibm.minipay"
				,".afp"=>"application/vnd.ibm.modcap"
				,".irm"=>"application/vnd.ibm.rights-management"
				,".sc"=>"application/vnd.ibm.secure-container"
				,".icc"=>"application/vnd.iccprofile"
				,".igl"=>"application/vnd.igloader"
				,".ivp"=>"application/vnd.immervision-ivp"
				,".ivu"=>"application/vnd.immervision-ivu"
				,".igm"=>"application/vnd.insors.igm"
				,".xpw"=>"application/vnd.intercon.formnet"
				,".i2g"=>"application/vnd.intergeo"
				,".qbo"=>"application/vnd.intu.qbo"
				,".qfx"=>"application/vnd.intu.qfx"
				,".rcprofile"=>"application/vnd.ipunplugged.rcprofile"
				,".irp"=>"application/vnd.irepository.package+xml"
				,".xpr"=>"application/vnd.is-xpr"
				,".fcs"=>"application/vnd.isac.fcs"
				,".jam"=>"application/vnd.jam"
				,".rms"=>"application/vnd.jcp.javame.midlet-rms"
				,".jisp"=>"application/vnd.jisp"
				,".joda"=>"application/vnd.joost.joda-archive"
				,".ktz"=>"application/vnd.kahootz"
				,".karbon"=>"application/vnd.kde.karbon"
				,".chrt"=>"application/vnd.kde.kchart"
				,".kfo"=>"application/vnd.kde.kformula"
				,".flw"=>"application/vnd.kde.kivio"
				,".kon"=>"application/vnd.kde.kontour"
				,".kpr"=>"application/vnd.kde.kpresenter"
			    ,".ksp"=>"application/vnd.kde.kspread"
				,".kwd"=>"application/vnd.kde.kword"
				,".htke"=>"application/vnd.kenameaapp"
				,".kia"=>"application/vnd.kidspiration"
				,".kne"=>"application/vnd.kinar"
				,".skp"=>"application/vnd.koan"
				,".sse"=>"application/vnd.kodak-descriptor"
				,".lasxml"=>"application/vnd.las.las+xml"
				,".lbd"=>"application/vnd.llamagraphics.life-balance.desktop"
				,".lbe"=>"application/vnd.llamagraphics.life-balance.exchange+xml"
				,".123"=>"application/vnd.lotus-1-2-3"
				,".apr"=>"application/vnd.lotus-approach"
				,".pre"=>"application/vnd.lotus-freelance"
				,".nsf"=>"application/vnd.lotus-notes"
				,".org"=>"application/vnd.lotus-organizer"
				,".scm"=>"application/vnd.lotus-screencam"
				,".lwp"=>"application/vnd.lotus-wordpro"
				,".portpkg"=>"application/vnd.macports.portpkg"
				,".mcd"=>"application/vnd.mcd"
				,".mc1"=>"application/vnd.medcalcdata"
				,".cdkey"=>"application/vnd.mediastation.cdkey"
				,".mwf"=>"application/vnd.mfer"
				,".mfm"=>"application/vnd.mfmp"
				,".flo"=>"application/vnd.micrografx.flo"
				,".igx"=>"application/vnd.micrografx.igx"
				,".mif"=>"application/vnd.mif"
				,".daf"=>"application/vnd.mobius.daf"
				,".dis"=>"application/vnd.mobius.dis"
				,".mbk"=>"application/vnd.mobius.mbk"
				,".mqy"=>"application/vnd.mobius.mqy"
				,".msl"=>"application/vnd.mobius.msl"
				,".plc"=>"application/vnd.mobius.plc"
				,".txf"=>"application/vnd.mobius.txf"
				,".mpn"=>"application/vnd.mophun.application"
				,".mpc"=>"application/vnd.mophun.certificate"
				,".xul"=>"application/vnd.mozilla.xul+xml"
				,".cil"=>"application/vnd.ms-artgalry"
				,".cab"=>"application/vnd.ms-cab-compressed"
				,".xls"=>"application/vnd.ms-excel"
				,".xlam"=>"application/vnd.ms-excel.addin.macroenabled.12"
				,".xlsb"=>"application/vnd.ms-excel.sheet.binary.macroenabled.12"
				,".xlsm"=>"application/vnd.ms-excel.sheet.macroenabled.12"
				,".xltm"=>"application/vnd.ms-excel.template.macroenabled.12"
				,".eot"=>"application/vnd.ms-fontobject"
				,".chm"=>"application/vnd.ms-htmlhelp"
				,".ims"=>"application/vnd.ms-ims"
				,".lrm"=>"application/vnd.ms-lrm"
				,".thmx"=>"application/vnd.ms-officetheme"
				,".cat"=>"application/vnd.ms-pki.seccat"
				,".stl"=>"application/vnd.ms-pki.stl"
				,".ppt"=>"application/vnd.ms-powerpoint"
				,".ppam"=>"application/vnd.ms-powerpoint.addin.macroenabled.12"
				,".pptm"=>"application/vnd.ms-powerpoint.presentation.macroenabled.12"
				,".sldm"=>"application/vnd.ms-powerpoint.slide.macroenabled.12"
				,".ppsm"=>"application/vnd.ms-powerpoint.slideshow.macroenabled.12"
				,".potm"=>"application/vnd.ms-powerpoint.template.macroenabled.12"
				,".mpp"=>"application/vnd.ms-project"
				,".docm"=>"application/vnd.ms-word.document.macroenabled.12"
				,".dotm"=>"application/vnd.ms-word.template.macroenabled.12"
				,".wps"=>"application/vnd.ms-works"
				,".wpl"=>"application/vnd.ms-wpl"
				,".xps"=>"application/vnd.ms-xpsdocument"
				,".mseq"=>"application/vnd.mseq"
				,".mus"=>"application/vnd.musician"
				,".msty"=>"application/vnd.muvee.style"
				,".nlu"=>"application/vnd.neurolanguage.nlu"
				,".nnd"=>"application/vnd.noblenet-directory"
				,".nns"=>"application/vnd.noblenet-sealer"
				,".nnw"=>"application/vnd.noblenet-web"
				,".ngdat"=>"application/vnd.nokia.n-gage.data"
				,".n-gage"=>"application/vnd.nokia.n-gage.symbian.install"
				,".rpst"=>"application/vnd.nokia.radio-preset"
				,".rpss"=>"application/vnd.nokia.radio-presets"
				,".edm"=>"application/vnd.novadigm.edm"
				,".edx"=>"application/vnd.novadigm.edx"
				,".ext"=>"application/vnd.novadigm.ext"
				,".odc"=>"application/vnd.oasis.opendocument.chart"
				,".otc"=>"application/vnd.oasis.opendocument.chart-template"
				,".odb"=>"application/vnd.oasis.opendocument.database"
				,".odf"=>"application/vnd.oasis.opendocument.formula"
				,".odft"=>"application/vnd.oasis.opendocument.formula-template"
				,".odg"=>"application/vnd.oasis.opendocument.graphics"
				,".otg"=>"application/vnd.oasis.opendocument.graphics-template"
				,".odi"=>"application/vnd.oasis.opendocument.image"
				,".oti"=>"application/vnd.oasis.opendocument.image-template"
				,".odp"=>"application/vnd.oasis.opendocument.presentation"
				,".otp"=>"application/vnd.oasis.opendocument.presentation-template"
				,".ods"=>"application/vnd.oasis.opendocument.spreadsheet"
				,".ots"=>"application/vnd.oasis.opendocument.spreadsheet-template"
				,".odt"=>"application/vnd.oasis.opendocument.text"
				,".odm"=>"application/vnd.oasis.opendocument.text-master"
				,".ott"=>"application/vnd.oasis.opendocument.text-template"
				,".oth"=>"application/vnd.oasis.opendocument.text-web"
				,".xo"=>"application/vnd.olpc-sugar"
				,".dd2"=>"application/vnd.oma.dd2+xml"
				,".oxt"=>"application/vnd.openofficeorg.extension"
				,".pptx"=>"application/vnd.openxmlformats-officedocument.presentationml.presentation"
				,".sldx"=>"application/vnd.openxmlformats-officedocument.presentationml.slide"
				,".ppsx"=>"application/vnd.openxmlformats-officedocument.presentationml.slideshow"
				,".potx"=>"application/vnd.openxmlformats-officedocument.presentationml.template"
				,".xlsx"=>"application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
				,".xltx"=>"application/vnd.openxmlformats-officedocument.spreadsheetml.template"
				,".docx"=>"application/vnd.openxmlformats-officedocument.wordprocessingml.document"
				,".dotx"=>"application/vnd.openxmlformats-officedocument.wordprocessingml.template"
				,".mgp"=>"application/vnd.osgeo.mapguide.package"
				,".dp"=>"application/vnd.osgi.dp"
				,".pdb"=>"application/vnd.palm"
				,".paw"=>"application/vnd.pawaafile"
				,".str"=>"application/vnd.pg.format"
				,".ei6"=>"application/vnd.pg.osasli"
				,".efif"=>"application/vnd.picsel"
				,".wg"=>"application/vnd.pmi.widget"
				,".plf"=>"application/vnd.pocketlearn"
				,".pbd"=>"application/vnd.powerbuilder6"
				,".box"=>"application/vnd.previewsystems.box"
				,".mgz"=>"application/vnd.proteus.magazine"
				,".qps"=>"application/vnd.publishare-delta-tree"
				,".ptid"=>"application/vnd.pvi.ptid1"
				,".qxd"=>"application/vnd.quark.quarkxpress"
				,".bed"=>"application/vnd.realvnc.bed"
				,".mxl"=>"application/vnd.recordare.musicxml"
				,".musicxml"=>"application/vnd.recordare.musicxml+xml"
				,".cryptonote"=>"application/vnd.rig.cryptonote"
				,".cod"=>"application/vnd.rim.cod"
				,".rm"=>"application/vnd.rn-realmedia"
				,".link66"=>"application/vnd.route66.link66+xml"
				,".st"=>"application/vnd.sailingtracker.track"
				,".see"=>"application/vnd.seemail"
				,".sema"=>"application/vnd.sema"
				,".semd"=>"application/vnd.semd"
				,".semf"=>"application/vnd.semf"
				,".ifm"=>"application/vnd.shana.informed.formdata"
				,".itp"=>"application/vnd.shana.informed.formtemplate"
				,".iif"=>"application/vnd.shana.informed.interchange"
				,".ipk"=>"application/vnd.shana.informed.package"
				,".twd"=>"application/vnd.simtech-mindmapper"
				,".mmf"=>"application/vnd.smaf"
				,".teacher"=>"application/vnd.smart.teacher"
				,".sdkm"=>"application/vnd.solent.sdkm+xml"
				,".dxp"=>"application/vnd.spotfire.dxp"
				,".sfs"=>"application/vnd.spotfire.sfs"
				,".sdc"=>"application/vnd.stardivision.calc"
				,".sda"=>"application/vnd.stardivision.draw"
				,".sdd"=>"application/vnd.stardivision.impress"
				,".smf"=>"application/vnd.stardivision.math"
				,".sdw"=>"application/vnd.stardivision.writer"
				,".sgl"=>"application/vnd.stardivision.writer-global"
				,".sm"=>"application/vnd.stepmania.stepchart"
				,".sxc"=>"application/vnd.sun.xml.calc"
				,".stc"=>"application/vnd.sun.xml.calc.template"
				,".sxd"=>"application/vnd.sun.xml.draw"
				,".std"=>"application/vnd.sun.xml.draw.template"
				,".sxi"=>"application/vnd.sun.xml.impress"
				,".sti"=>"application/vnd.sun.xml.impress.template"
				,".sxm"=>"application/vnd.sun.xml.math"
				,".sxw"=>"application/vnd.sun.xml.writer"
				,".sxg"=>"application/vnd.sun.xml.writer.global"
				,".stw"=>"application/vnd.sun.xml.writer.template"
				,".sus"=>"application/vnd.sus-calendar"
				,".svd"=>"application/vnd.svd"
				,".sis"=>"application/vnd.symbian.install"
				,".xsm"=>"application/vnd.syncml+xml"
				,".bdm"=>"application/vnd.syncml.dm+wbxml"
				,".xdm"=>"application/vnd.syncml.dm+xml"
				,".tao"=>"application/vnd.tao.intent-module-archive"
				,".tmo"=>"application/vnd.tmobile-livetv"
				,".tpt"=>"application/vnd.trid.tpt"
				,".mxs"=>"application/vnd.triscape.mxs"
				,".tra"=>"application/vnd.trueapp"
				,".ufd"=>"application/vnd.ufdl"
				,".utz"=>"application/vnd.uiq.theme"
				,".umj"=>"application/vnd.umajin"
				,".unityweb"=>"application/vnd.unity"
				,".uoml"=>"application/vnd.uoml+xml"
				,".vcx"=>"application/vnd.vcx"
				,".vsd"=>"application/vnd.visio"
				,".vis"=>"application/vnd.visionary"
				,".vsf"=>"application/vnd.vsf"
				,".wbxml"=>"application/vnd.wap.wbxml"
				,".wmlc"=>"application/vnd.wap.wmlc"
				,".wmlsc"=>"application/vnd.wap.wmlscriptc"
				,".wtb"=>"application/vnd.webturbo"
				,".nbp"=>"application/vnd.wolfram.player"
				,".wpd"=>"application/vnd.wordperfect"
				,".wqd"=>"application/vnd.wqd"
				,".stf"=>"application/vnd.wt.stf"
				,".xar"=>"application/vnd.xara"
				,".xfdl"=>"application/vnd.xfdl"
				,".hvd"=>"application/vnd.yamaha.hv-dic"
				,".hvs"=>"application/vnd.yamaha.hv-script"
				,".hvp"=>"application/vnd.yamaha.hv-voice"
				,".osf"=>"application/vnd.yamaha.openscoreformat"
				,".osfpvg"=>"application/vnd.yamaha.openscoreformat.osfpvg+xml"
				,".saf"=>"application/vnd.yamaha.smaf-audio"
				,".spf"=>"application/vnd.yamaha.smaf-phrase"
				,".cmp"=>"application/vnd.yellowriver-custom-menu"
				,".zir"=>"application/vnd.zul"
				,".zaz"=>"application/vnd.zzazz.deck+xml"
				,".vxml"=>"application/voicexml+xml"
				,".wgt"=>"application/widget"
				,".hlp"=>"application/winhlp"
				,".wsdl"=>"application/wsdl+xml"
				,".wspolicy"=>"application/wspolicy+xml"
				,".7z"=>"application/x-7z-compressed"
				,".abw"=>"application/x-abiword"
				,".ace"=>"application/x-ace-compressed"
				,".aab"=>"application/x-authorware-bin"
				,".aam"=>"application/x-authorware-map"
				,".aas"=>"application/x-authorware-seg"
				,".bcpio"=>"application/x-bcpio"
				,".torrent"=>"application/x-bittorrent"
				,".bz"=>"application/x-bzip"
				,".bz2"=>"application/x-bzip2"
				,".vcd"=>"application/x-cdlink"
				,".chat"=>"application/x-chat"
				,".pgn"=>"application/x-chess-pgn"
				,".cpio"=>"application/x-cpio"
				,".csh"=>"application/x-csh"
				,".deb"=>"application/x-debian-package"
				,".dir"=>"application/x-director"
				,".wad"=>"application/x-doom"
				,".ncx"=>"application/x-dtbncx+xml"
				,".dtb"=>"application/x-dtbook+xml"
				,".res"=>"application/x-dtbresource+xml"
				,".dvi"=>"application/x-dvi"
				,".bdf"=>"application/x-font-bdf"
				,".gsf"=>"application/x-font-ghostscript"
				,".psf"=>"application/x-font-linux-psf"
				,".otf"=>"application/x-font-otf"
				,".pcf"=>"application/x-font-pcf"
				,".snf"=>"application/x-font-snf"
				,".ttf"=>"application/x-font-ttf"
				,".pfa"=>"application/x-font-type1"
				,".woff"=>"application/x-font-woff"
				,".spl"=>"application/x-futuresplash"
				,".gnumeric"=>"application/x-gnumeric"
				,".gtar"=>"application/x-gtar"
				,".hdf"=>"application/x-hdf"
				,".jnlp"=>"application/x-java-jnlp-file"
				,".latex"=>"application/x-latex"
				,".prc"=>"application/x-mobipocket-ebook"
				,".application"=>"application/x-ms-application"
				,".wmd"=>"application/x-ms-wmd"
				,".wmz"=>"application/x-ms-wmz"
				,".xbap"=>"application/x-ms-xbap"
				,".mdb"=>"application/x-msaccess"
				,".obd"=>"application/x-msbinder"
				,".crd"=>"application/x-mscardfile"
				,".clp"=>"application/x-msclip"
				,".exe"=>"application/x-msdownload"
				,".mvb"=>"application/x-msmediaview"
				,".wmf"=>"application/x-msmetafile"
				,".mny"=>"application/x-msmoney"
				,".pub"=>"application/x-mspublisher"
				,".scd"=>"application/x-msschedule"
				,".trm"=>"application/x-msterminal"
				,".wri"=>"application/x-mswrite"
				,".nc"=>"application/x-netcdf"
				,".p12"=>"application/x-pkcs12"
				,".p7b"=>"application/x-pkcs7-certificates"
				,".p7r"=>"application/x-pkcs7-certreqresp"
				,".rar"=>"application/x-rar-compressed"
				,".sh"=>"application/x-sh"
				,".shar"=>"application/x-shar"
				,".swf"=>"application/x-shockwave-flash"
				,".xap"=>"application/x-silverlight-app"
				,".sit"=>"application/x-stuffit"
				,".sitx"=>"application/x-stuffitx"
				,".sv4cpio"=>"application/x-sv4cpio"
				,".sv4crc"=>"application/x-sv4crc"
				,".tar"=>"application/x-tar"
				,".tcl"=>"application/x-tcl"
				,".tex"=>"application/x-tex"
				,".tfm"=>"application/x-tex-tfm"
				,".texinfo"=>"application/x-texinfo"
				,".ustar"=>"application/x-ustar"
				,".src"=>"application/x-wais-source"
				,".der"=>"application/x-x509-ca-cert"
				,".fig"=>"application/x-xfig"
				,".xpi"=>"application/x-xpinstall"
				,".xdf"=>"application/xcap-diff+xml"
				,".xenc"=>"application/xenc+xml"
				,".xhtml"=>"application/xhtml+xml"
				,".xml"=>"application/xml"
				,".dtd"=>"application/xml-dtd"
				,".xop"=>"application/xop+xml"
				,".xslt"=>"application/xslt+xml"
				,".xspf"=>"application/xspf+xml"
				,".mxml"=>"application/xv+xml"
				,".yang"=>"application/yang"
				,".yin"=>"application/yin+xml"
				,".zip"=>"application/zip"
				,".adp"=>"audio/adpcm"
				,".au"=>"audio/basic"
				,".mid"=>"audio/midi"
				,".mp4a"=>"audio/mp4"
				,".mpga"=>"audio/mpeg"
				,".oga"=>"audio/ogg"
				,".uva"=>"audio/vnd.dece.audio"
				,".eol"=>"audio/vnd.digital-winds"
				,".dra"=>"audio/vnd.dra"
				,".dts"=>"audio/vnd.dts"
				,".dtshd"=>"audio/vnd.dts.hd"
				,".lvp"=>"audio/vnd.lucent.voice"
				,".pya"=>"audio/vnd.ms-playready.media.pya"
				,".ecelp4800"=>"audio/vnd.nuera.ecelp4800"
				,".ecelp7470"=>"audio/vnd.nuera.ecelp7470"
				,".ecelp9600"=>"audio/vnd.nuera.ecelp9600"
				,".rip"=>"audio/vnd.rip"
				,".weba"=>"audio/webm"
				,".aac"=>"audio/x-aac"
				,".aif"=>"audio/x-aiff"
				,".m3u"=>"audio/x-mpegurl"
				,".wax"=>"audio/x-ms-wax"
				,".wma"=>"audio/x-ms-wma"
				,".ram"=>"audio/x-pn-realaudio"
				,".rmp"=>"audio/x-pn-realaudio-plugin"
				,".wav"=>"audio/x-wav"
				,".mp3"=>"audio/mpeg"
				,".mka"=>"audio/x-matroska"
				,".cdx"=>"chemical/x-cdx"
				,".cif"=>"chemical/x-cif"
				,".cmdf"=>"chemical/x-cmdf"
				,".cml"=>"chemical/x-cml"
				,".csml"=>"chemical/x-csml"
				,".xyz"=>"chemical/x-xyz"
				,".bmp"=>"image/bmp"
				,".cgm"=>"image/cgm"
				,".g3"=>"image/g3fax"
				,".gif"=>"image/gif"
				,".ief"=>"image/ief"
				,".jpeg"=>"image/jpeg"
				,".jpg"=>"image/jpeg"
				,".ktx"=>"image/ktx"
				,".png"=>"image/png"
				,".btif"=>"image/prs.btif"
				,".svg"=>"image/svg+xml"
				,".tiff"=>"image/tiff"
				,".psd"=>"image/vnd.adobe.photoshop"
				,".uvi"=>"image/vnd.dece.graphic"
				,".djvu"=>"image/vnd.djvu"
				,".sub"=>"image/vnd.dvb.subtitle"
				,".dwg"=>"image/vnd.dwg"
				,".dxf"=>"image/vnd.dxf"
				,".fbs"=>"image/vnd.fastbidsheet"
				,".fpx"=>"image/vnd.fpx"
				,".fst"=>"image/vnd.fst"
				,".mmr"=>"image/vnd.fujixerox.edmics-mmr"
				,".rlc"=>"image/vnd.fujixerox.edmics-rlc"
				,".mdi"=>"image/vnd.ms-modi"
				,".npx"=>"image/vnd.net-fpx"
				,".wbmp"=>"image/vnd.wap.wbmp"
				,".xif"=>"image/vnd.xiff"
				,".webp"=>"image/webp"
				,".ras"=>"image/x-cmu-raster"
				,".cmx"=>"image/x-cmx"
				,".fh"=>"image/x-freehand"
				,".ico"=>"image/x-icon"
				,".pcx"=>"image/x-pcx"
				,".pic"=>"image/x-pict"
				,".pnm"=>"image/x-portable-anymap"
				,".pbm"=>"image/x-portable-bitmap"
				,".pgm"=>"image/x-portable-graymap"
				,".ppm"=>"image/x-portable-pixmap"
				,".rgb"=>"image/x-rgb"
				,".xbm"=>"image/x-xbitmap"
				,".xpm"=>"image/x-xpixmap"
				,".xwd"=>"image/x-xwindowdump"
				,".eml"=>"message/rfc822"
				,".igs"=>"model/iges"
				,".msh"=>"model/mesh"
				,".dae"=>"model/vnd.collada+xml"
				,".dwf"=>"model/vnd.dwf"
				,".gdl"=>"model/vnd.gdl"
				,".gtw"=>"model/vnd.gtw"
				,".mts"=>"model/vnd.mts"
				,".vtu"=>"model/vnd.vtu"
				,".wrl"=>"model/vrml"
				,".ics"=>"text/calendar"
				,".css"=>"text/css"
				,".csv"=>"text/csv"
				,".html"=>"text/html"
				,".htm"=>"text/html"
				,".php"=>"text/html"
				,".asp"=>"text/html"
				,".aspx"=>"text/html"
				,".pl"=>"text/html"
				,".cgi"=>"text/html"
				,"."=>"text/html"
				,".n3"=>"text/n3"
				,".txt"=>"text/plain"
				,".par"=>"text/plain-bas"
				,".dsc"=>"text/prs.lines.tag"
				,".rtx"=>"text/richtext"
				,".sgml"=>"text/sgml"
				,".tsv"=>"text/tab-separated-values"
				,".t"=>"text/troff"
				,".ttl"=>"text/turtle"
				,".uri"=>"text/uri-list"
				,".curl"=>"text/vnd.curl"
				,".dcurl"=>"text/vnd.curl.dcurl"
				,".mcurl"=>"text/vnd.curl.mcurl"
				,".scurl"=>"text/vnd.curl.scurl"
				,".fly"=>"text/vnd.fly"
				,".flx"=>"text/vnd.fmi.flexstor"
				,".gv"=>"text/vnd.graphviz"
				,".3dml"=>"text/vnd.in3d.3dml"
				,".spot"=>"text/vnd.in3d.spot"
				,".jad"=>"text/vnd.sun.j2me.app-descriptor"
				,".wml"=>"text/vnd.wap.wml"
				,".wmls"=>"text/vnd.wap.wmlscript"
				,".s"=>"text/x-asm"
				,".c"=>"text/x-c"
				,".f"=>"text/x-fortran"
				,".java"=>"text/x-java-source"
				,".p"=>"text/x-pascal"
				,".etx"=>"text/x-setext"
				,".uu"=>"text/x-uuencode"
				,".vcs"=>"text/x-vcalendar"
				,".vcf"=>"text/x-vcard"
				,".yaml"=>"text/yaml"
				,".3gp"=>"video/3gpp"
				,".3g2"=>"video/3gpp2"
				,".h261"=>"video/h261"
				,".h263"=>"video/h263"
				,".h264"=>"video/h264"
				,".jpgv"=>"video/jpeg"
				,".jpm"=>"video/jpm"
				,".mj2"=>"video/mj2"
				,".mp4"=>"video/mp4"
				,".mpeg"=>"video/mpeg"
				,".ogv"=>"video/ogg"
				,".qt"=>"video/quicktime"
				,".uvh"=>"video/vnd.dece.hd"
				,".uvm"=>"video/vnd.dece.mobile"
				,".uvp"=>"video/vnd.dece.pd"
				,".uvs"=>"video/vnd.dece.sd"
				,".uvv"=>"video/vnd.dece.video"
				,".fvt"=>"video/vnd.fvt"
				,".mxu"=>"video/vnd.mpegurl"
				,".pyv"=>"video/vnd.ms-playready.media.pyv"
				,".uvu"=>"video/vnd.uvvu.mp4"
				,".viv"=>"video/vnd.vivo"
				,".webm"=>"video/webm"
				,".f4v"=>"video/x-f4v"
				,".fli"=>"video/x-fli"
				,".flv"=>"video/x-flv"
				,".m4v"=>"video/x-m4v"
				,".asf"=>"video/x-ms-asf"
				,".wm"=>"video/x-ms-wm"
				,".wmv"=>"video/x-ms-wmv"
				,".wmx"=>"video/x-ms-wmx"
				,".wvx"=>"video/x-ms-wvx"
				,".avi"=>"video/x-msvideo"
				,".movie"=>"video/x-sgi-movie"
				,".mkv"=>"video/x-matroska"
				,".ice"=>"x-conference/x-cooltalk"
				,".cs"=>"text/plain"
				,".vb"=>"text/plain"
				,".py"=>"text/plain"
        );
		$parsed = parse_url($url);
		@ $path = pathinfo($parsed['path']);
        $extension = ".".@$path['extension'];
		if(isset($mime_types[$extension]))
        {
		 return $mime_types[$extension];
		}
		else
		{
			return "other";
		}
}
function keywords($text, $tagd)
{
	global $keyd;
	$keys = preg_split('/[\s,\-\.]+/si', $text);
	$keysc = count($keys);
	$dec = $tagd/$keysc;
	foreach($keys as $index => $value)
	{
		if(trim($value) != "") @ $keyd[$value] += $tagd - $index*$dec;
	}	
}
function pr($url, $db)
{
	$pr = 0;
	$query = "select * from links
	          where link = '$url'";
    $result = mysqli_query($db, $query);
	if($result)
	{
	 $t = mysqli_num_rows($result);
	 $i = 0;
	 while($pr==0 && $t>1)
	 {
		$ai = pow(5, $i);
		$bi = pow(5, $i+1);
		if($t>=$ai && $t<$bi)
		{
			$pr = $i + ($t-$ai)/($bi-$ai);
		}
		$i++;
	 }
	}
	return $pr;
}
function show($keys, $ar)
{
			 $show = "";
			 $n = 0;
			 $c = count($keys);
			 if(empty($keys))
			 {
				 for($i=0; $i<=40; $i++)
				 {
					 	if(!empty($ar[$i]))
						{
						 $show .= trim($ar[$i])." ";
						 $end = $i;
						}
				 }
				 if($end!=(count($ar)-1)) $show .= "... ";
				 $show_now = $show;
			 }
			 else
			 {
			  if(($keys[0]+1)<=10)
			  {
				 for($k=0; $k<=($keys[0]-1); $k++)
				 {
					 $show .= trim($ar[$k])." ";
				 }
			  }
			  else
			  {
				 $show .= "... ";
			  }
			  $show_now = $show;
			  for($k=0;$k<$c;$k++)
			  {
				 $ke = $keys[$k];
				 $show .= '<b>'.trim($ar[$ke]).'</b> ';
				 $t = @$keys[$k+1]-$keys[$k];
				 if($t<=10 && $t>0)
				 {
					 for($i = $keys[$k]+1; $i<@$keys[$k+1]; $i++)
					 {
						 $show .= trim($ar[$i])." ";
					 }
				 }
				 else
				 {
					 $end = $keys[$k];
					 for($i=1;$i<=5;$i++)
					 {
						 $key = $keys[$k]+$i;
						 if(isset($ar[$key]))
						 {
						  $show .= trim($ar[$key])." ";
						  $end = $key;
						 }
					 }
					 if($end!=(count($ar)-1)) $show .= "... ";
				 }
				 $n++;
				 if($n<=5)
				 {
					 $show_now = $show;
				 }
			  }
			 }
			 return $show_now;
}
function page($search, $page, $count, $num)
{
	$t = (integer)($count/$num);
	if($count%$num != 0) $t++; 
	if($t>1)
	{
	 ?>
     <table align="center" class="page">
     <tr>
	 <?php
	 if($page>1)
	 {
		 ?>
         <td><a href="?search=<?php echo $search ?>&page=<?php echo $page-1 ?>"><?php echo "<"; ?></a></td>
         <?php
	 }
	 for($k = 1; $k<=$t; $k++)
	 {
		 if(abs($page-$k)<5)
		 {
		  echo '<td>';
		  if($k == $page)
		  {
			 ?><font style="color: green;">o</font><?php
		  }
		  else
		  {
			 ?>
             <a href="?search=<?php echo $search ?>&page=<?php echo $k ?>"><font style="color: red;">o</font></a>
			 <?php
		  }
		  echo '</td>';
		 }
	 }
	 ?><td class="page"><font style="color: red;">Paar</font></td><?php
	 if($page<$t)
	 {
		 ?>
         <td><a href="?search=<?php echo $search ?>&page=<?php echo $page+1 ?>"><?php echo ">"; ?></a></td>
		 <?php
	 }
	 ?>
     </tr>
     <tr>
	 <?php
	 if($page>1)
	 {
		 ?>
         <td><a href="?search=<?php echo $search ?>&page=<?php echo $page-1 ?>">قبلی</a></td>
         <?php
	 }
	 for($k = 1; $k<=$t; $k++)
	 {
		if(abs($page-$k)<5)
		{
		 echo '<td align="center">';
		 if($k == $page)
		 {
			 echo '<b>'.$k.'</b>';
		 }
		 else
		 {
			 ?>
             <a href="?search=<?php echo $search ?>&page=<?php echo $k ?>"><?php echo $k ?></a>
			 <?php
		 }
		 echo '</td>';
		}
	 }
	 ?><td></td><?php
	 if($page<$t)
	 {
		 ?>
         <td><a href="?search=<?php echo $search ?>&page=<?php echo $page+1 ?>">بعدی</a></td>
		 <?php
	 }
	 ?>
     </tr>
     </table>
     <?php
	}
}
function menu($now, $search)
{
    ?>
    <div class="Rectangle1">
        <div class="Rectangle2">
            <table align="center" class="Rectangle3" cellpadding="0" cellspacing="0">
            <tr>
                <?php
                $links = array('/' => 'جستجو', '/photos/' => 'عکس ها', '/songs/' => 'آهنگ ها', '/videos/' => 'فیلم ها', '/books/' => 'کتاب ها');
                foreach($links as $index => $value) {
                    if($now == $index) {
                        echo '<td class="menus" align="center"><a href="'.$index.'?search='.$search.'">'.$value.'</a></td>';
                    }
                    else {
                        echo '<td class="menu" align="center"><a href="'.$index.'?search='.$search.'">'.$value.'</a></td>';
                    }
                }
                ?>
            </tr>
            </table>
        </div>
    </div>
        <?php
		if(trim($search)=="")
		{
            ?>
            <div align="center" class="Layer3">
                <img src="/images/logo.png"><br /><font style="font-size: 36px;">موتور جستجوی ایرانی برای همه!!!</font>
                <form method="get">
                    <table>
                        <tr>
                            <td>
                                <input name="search" class="RoundedRectangle1" type="text" placeholder="عبارت جستجو را این جا تایپ کنید ...">
                            </td>
                            <td>
                                <input class="RoundedRectangle2" type="submit" value="بگرد">
                            </td>
                        </tr>
                    </table>
                </form>
            </div>
            <?php
		}
		else
		{
			?>
            <form method="get">
            <table>
                <tr>
                    <td>
                        <img src="/images/logo.png">
                    </td>
                    <td>
                        <input name="search" class="RoundedRectangle1" type="text" placeholder="عبارت جستجو را این جا تایپ کنید ..." value="<?php echo $search; ?>">
                    </td>
                    <td>
                        <input class="RoundedRectangle2" type="submit" value="بگرد">
                    </td>
                </tr>
            </table>
            </form>
			<?php
		}
}
function foot()
{
	?>
    <div align="center" class="AllRightsReservedfor">All Rights Reserved for&nbsp;<a href="/">PAAROO.IR</a></div>
    </div>	
	<?php
}
?>