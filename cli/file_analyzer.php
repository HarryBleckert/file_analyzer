<?php
// This file is a file analyzing tool and report plugin for Moodle - http://moodle.org/
//
//#!/usr/bin/php

/**
 *
 * file name   file_analyzer
 * @category   admin
 * @copyright  2019-2021 Harry Bleckert for ASH Berlin <Harry.Bleckert@ASH-Berlin.eu>
 */


/*

    Plugin ASH file_analyzer

	 To Do:
	 - convert code from procedural to OO
	 
    Table mdl_files:
    id
    contenthash
    pathnamehash
    contextid
    component
    filearea
    itemid
    filepath
    filename
    userid
    filesize
    mimetype
    status
    source
    author
    license
    timecreated
    timemodified
    sortorder
    referencefileid
*/


// initial variable definitions



$AppName = "ASH Moodle File Analyzer";
$AppVersion = "25.08.24";


$isCli = (php_sapi_name() == "cli");  // cli running
if ( isset($_SERVER['REQUEST_SCHEME'] ) && !$isCli )
{	// web root folder for App & www files
	$selfFolder = dirname($_SERVER['SCRIPT_FILENAME'])."/";
	// own url
	$selfURL = $_SERVER['REQUEST_SCHEME'] . "://" . $_SERVER['SERVER_NAME'] . ":" . $_SERVER['SERVER_PORT'] . dirname($_SERVER['SCRIPT_NAME'])."/";
}
else
{	// web root folder for App & www files
	$selfFolder = "/data/var/www/moodle_production/report/file_analyzer/";
	// own url
	$selfURL = "https://moodle.ash-berlin.eu/report/file_analyzer/";
}
$outFolder = $selfFolder . "results/";
$tmpFolder = $outFolder . "tmp/";
$downloadFolder = $tmpFolder . "download/";
$downloadURL = $selfURL . "results/tmp/download";
$ResultsURL = $selfURL . "results/";
if ( !is_dir( $outFolder ) )
{	mkdir( $outFolder ); }
if ( !is_dir( $tmpFolder ) )
{	mkdir( $tmpFolder ); }
if ( !is_dir( $downloadFolder ) )
{	mkdir( $downloadFolder ); }
// purge download folder
exec("rm $downloadFolder"."*&2>/dev/null");

// supposedly not show sidebars, but not working!!! see: https://moodle.org/mod/forum/discuss.php?d=346967
$navdraweropen = false;  // moodle internal setting


$AppTag = '<p><br><a href="'.$selfURL.'"><span style="fontweight:bold;font-size:125%;">'.$AppName.'</span></a>';

// define heml parts
$htmlHeader = defined('MOODLE_INTERNAL') ?"" :'<!DOCTYPE html>
<html dir="ltr" lang="en" xml:lang="en">
<head>
<title>'.$AppName.'</title>
<meta http-equiv="Content-type" content="text/html; charset=UTF-8">
<meta name="AUTHOR" content="ASH Berlin">
</head>
<body>
';

$htmlFooter = defined('MOODLE_INTERNAL') ?"" :"\n</body>\n</html>\n";
$tableStyle = "
<style>
.showgrid, .showgrid tr, .showgrid th, .showgrid td {
	font-size: 1em; 
	text-align: left;
	border-width: 2px;
	padding: 0px 4px;
	/*border-style: none;*/
	border: 1px solid black;
	border-bottom: 1px solid #888;
	border-collapse: collapse;
	}
.showgrid tr:first-child{
	border-bottom: 3px solid #444;	
	}
.showgrid tr:nth-child(odd) {
	 background-color: #edebd5;
}
.showgrid tr:nth-child(even) {
		 background-color: #ffffff;
}
</style>
";




// Library functions

function HTMLheader()
{	global $htmlHeader, $tableStyle, $isCli,$AppTag;
	if (!$isCli )  
	{	echo $htmlHeader; }
	echo $tableStyle;
	if ( !$isCli ) { echo $AppTag; }
}

function HTMLfooter()
{	global $OUTPUT, $htmlFooter, $isCli, $AppTag, $AppVersion;
	if ( !$isCli ) 
	{	echo $AppTag."<br>".'<a href="ASH-Berlin.eu/" target="ASH"><b>ASH Berlin</b></a>'." - &copy;  2019-".date("Y")."<small> (Version: $AppVersion)</small>"; 
		echo $htmlFooter;
	}	
}

function getPDFImageData( $file="", $rawdata=false )
{	$ImageData = array();
	if ( empty($file) || !is_file( $file ) )
	{	return $ImageData; }
	$pdfimages = shell_exec( "/usr/bin/pdfimages -list $file" );
	if ( $rawdata )
	{	return $pdfimages; }
	if ( !stristr( $pdfimages, "image" ) )
	{	return $ImageData; }
	$pdfimages = preg_replace('/[[:blank:]]+/m', ' ',$pdfimages );
	$lines = explode( "\n", $pdfimages);
	$imageHeader = explode(" ", array_shift ( $lines ) );
	array_shift ( $lines ); array_pop($lines);
	for ($n = 0;$n<count($lines); $n++)
	{	$line = explode( " ", trim($lines[$n]) );
		for ($n2=0; $n2<count($line);$n2++ )
		{	$ImageData[($n+1)][$imageHeader[$n2]] = $line[$n2]; }
	}
	return $ImageData;
}	

function getPDFImagePPI( $file="noWay",  $ImageData = array() )
{	if ( empty($ImageData) ) { $ImageData = getPDFImageData( $file ); }
	$ppi = 0;
	if ( !count($ImageData) )
	{ return 0; }
	foreach ( $ImageData as $Image )
	{	$ppi += is_numeric($Image["x-ppi"]) ? $Image["x-ppi"] :0; }
	return intval($ppi/count($ImageData));
}

function showPDFImageRaw( $file="", $ImageData = array() )
{	if ( empty($ImageData) ) { $ImageData = getPDFImageData( $file, true ); }
	return $ImageData; 
}

function showPDFImageTable( $file="" )
{	$ImageData = getPDFImageData( $file );
	if ( empty( $ImageData ) )
	{	return ""; }
	$table = "<style>table{border-collapse: collapse;}table, th, td {border: 1px solid black;}</style><table>\n<tr>\n";
	// header
	foreach ( $ImageData[1] as $key => $val)
	{	$table .= "<td><b>". $key ."</b></td>"; }
	$table .= "</tr>\n";
	// rows
	foreach ( $ImageData as $Image )
	{	$table .= "<tr>\n";
		foreach ( $Image as $val)
		{	$align= "text-align:".(is_numeric( $val )?"right":"left").";";
			$table .= '<td style="'.$align.'">'. $val ."</td>"; }
		$table .= "</tr>\n";
	}
	$table .= "</table>\n";
	return $table;
}

// German decimals and separators
function number_formatDE( $val, $decimals=0)
{	return number_format( $val, $decimals, ",", "." ); }

function showResultFiles( $outFolder="" )
{	$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator( $outFolder, FilesystemIterator::SKIP_DOTS));
	$files = array();
	foreach ( $iterator as $file)
	{	if ( $file->isDir() || strstr($file->getPathname(), "/tmp" ) ) { continue; }
		$filename = $file->getFilename();
		if ( $filename == "index.php" || stristr( $filename, "tmp") ) { continue; }
		$files[$filename]["size"] = $file->getSize();
		$files[$filename]["date"] = $file->getMTime();
	}
	ksort( $files );
	foreach( $files as $key => $value)
	{	echo 	'<div style="display:block;" title="Size: '.number_formatDE($value["size"]/1024).'KB - Created: '.date("Y-m-d",$value["date"]).
				'"><a href="'.$GLOBALS["ResultsURL"].$key
				.'" target="results">'.$key.'</a></div>';
	}
}


function purgeAgedFiles( $outFolder="noWay/",$tmpFolder="noWay/" )
{	// cleanup aged client files 
	$storageTime = (60*60*24*80);  // 80 days
	// cleanup tmp folder
	$tmpStorage = (60*60*24); // 1 day
	if ( stristr( $tmpFolder, "tmp/" ) && ( !isset( $_Cookie["tmpStorage"] ) || $_Cookie["tmpStorage"] < time() ) )
	{	setcookie( "tmpStorage", time()+$tmpStorage, strtotime('+2 days') );
		$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator( $tmpFolder, FilesystemIterator::SKIP_DOTS));
		foreach ( $iterator as $file)
		{	$filename = $file->getPathname();
			if ( $file->isDir() ) { continue; }
			if ( basename($filename) == "index.php" ) { continue; }
			if ( filemtime( $filename ) < (time()-$tmpStorage) )
			{	unlink($filename); }
		}
	}
	if ( $outFolder && ( !isset( $_Cookie["PurgeAgedFiles"] ) || $_Cookie["PurgeAgedFiles"] < time() ) )
	{	setcookie( "PurgeAgedFiles", time()+$storageTime, strtotime('+2 month') );
		$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator( $outFolder, FilesystemIterator::SKIP_DOTS));
		foreach ( $iterator as $file)
		{	$filename = $file->getPathname();
			if ( $file->isDir() ) { continue; }
			if ( basename($filename) == "index.php" ) { continue; }
			if ( filemtime( $filename ) < (time()-$storageTime) )
			{	unlink($filename); }
		}
	}
}
//  end lib functions
purgeAgedFiles($outFolder, $tmpFolder);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('pluginname', 'report_file_analyzer'));


// start main 

// HANDLE FILE VIEW / INFO REQUESTS
if ( isset( $_REQUEST["file"]) && $_REQUEST["file"] )
{	$filename = trim($_REQUEST["file"]);
	$filepath = $_REQUEST["filepath"];
	$mimetype = $_REQUEST["mimetype"];
	$author = $_REQUEST["author"];
	$license = $_REQUEST["license"];
	$last_modified = $_REQUEST["last_modified"];
	$extPos = strrpos( $filename, ".");
	$extension = strtolower(substr( basename($filename), $extPos+1 ));
	$filebase =  substr( basename($filename), 0, $extPos );
	$tmpfile = $tmpFolder .str_replace(array(" ",",",":",";"),"_", $filename);
	$ResultsURL .= "tmp/";
	HTMLheader();
	// echo "<hr>$tmpfile - $selfFolder - $selfURL - $filename - $extension - $filepath - $mimetype<hr>";
	$fileInfo = "<h1>File: <b>$filename</b></h1>
					Author (Moodle): $author - License: $license<br>\n
					Last modified at: $last_modified".
					" - Size: ".number_formatDE(filesize($filepath)/1024)."KB - Mimetype: $mimetype<br>\n".
					"Storage: $filepath<hr>\n";
	
	if ( $extension == "pdf" || stristr( $mimetype, "pdf" ) )
	{	//$tmpfile .= str_replace(" ","_", $filename).(stristr( $filename, "pdf")?"":".pdf");
		exec( "cp $filepath \"$tmpfile\"");
		$ImageData = getPDFImageData( $tmpfile );
		if ( empty ($ImageData ) )
		{	$info = "<b>- No embedded images-</b>"; }
		else
		{	$info = '<a href="#images"><b>Embedded images</b>: '.count($ImageData)."</a>"; }
		$info .= "<hr>".shell_exec( "/usr/bin/pdfinfo \"$tmpfile\"" );
		echo 	$fileInfo .nl2br( $info )."<hr><p>";
		
		echo '<embed src="'.$ResultsURL.basename($tmpfile).'" width="100%" height="2100px" />';
		
		if ( !empty ($ImageData ) )
		{	echo '<p><hr><b>Embedded images</b>:<a name="images">'.showPDFImageTable( $tmpfile )."</p>"; }
		$info = shell_exec( "/usr/bin/pdfinfo -meta \"$tmpfile\"" );
		if ( empty($info) )
		{ echo "<p><hr><b>PDF Meta: ./.</b><br>"; }
		else
		{	echo "<p><hr><b>PDF Meta</b><br>"; echo nl2br( $info )."<hr><p>"; }
		if ( !empty ($ImageData ) )
		{	echo "<hr><b>PDF Text Content</b>:<br>". nl2br(shell_exec( "/usr/bin/pdftotext -q $tmpfile $tmpFolder/tmp.txt 2>/dev/null|cat $tmpFolder/tmp.txt")); }
	}
	elseif ( stristr( $mimetype, "image" ) )
	{	echo 	$fileInfo;
		//$tmpfile .= str_replace(" ","_", $filename);
		exec( "cp $filepath \"$tmpfile\"");
		echo '<img src="'.$ResultsURL.basename($tmpfile).'" title="'.$filename.'">'; 
	}
	elseif ( stristr( $mimetype, "html" ) || stristr( $mimetype, "csv" ) || $mimetype == "text" )
	{	echo 	$fileInfo;
		//$tmpfile .= str_replace(" ","_", $filename);
		exec( "cp $filepath \"$tmpfile\"");
		if ( stristr( $mimetype, "html" ) )
		{	echo file_get_contents($tmpfile);  }
		else
		{	echo nl2br(file_get_contents($tmpfile));  }
	}
	// Moodle backup file
	elseif ( $extension == "mbz" )
	{	echo 	$fileInfo;
		//$tmpfile .= str_replace(" ","_", $filename);
		exec( "cp $filepath \"$tmpfile\"");
		$results =  shell_exec("/bin/tar -ztvf \"$tmpfile\"");
		echo nl2br($results);  
	}
	// do a unoconv moodle.backup
	else
	{	//$filenametmpFile = $tmpfile . str_replace(" ","_", $filename);
		//$tmpfile .= str_replace(" ","_", $filename).".pdf";
		$cmd = "export UNO_PATH=/usr/lib/libreoffice;/usr/bin/timeout 120 /usr/bin/unoconv -T120 -l -n -v -f pdf -o \"$tmpfile\" \"$filepath\""; 
		$results	= shell_exec( $cmd );
        // echo "<hr>$cmd<br>Results<br>$results<hr>";
		echo $fileInfo;
		if ( is_file( $tmpfile ) )
		{	echo '<embed src="'.$ResultsURL.basename($tmpfile).'" width="100%" height="1700px" />'; }
		else
		{	exec( "cp $filepath \"$downloadFolder/$filename\"");
			echo "<br><a href=\"$downloadURL/$filename\"><b>Download<br>$filename</b></a><br>";			
			//echo $results; 
		}
	}
	HTMLfooter();
	//phpinfo();
	// done by purge: @unlink($tmpfile);
	if ( $isCli )
	{	exit; }
	else 
	{	return; }
}

$help = $isWeb = $isHTML = $orphaned = $show = $pdfImage = $pdfImageOnly = $pdfBookScannerOnly = false;

$instance = $filterExt = $filterMime = $filterDateFrom = $filterFilename = $filterUserID = $filterCourseID = $filterCourseShortname = $filterDateUntil = "";
$limit = $unlimited = 8888888888;
$OrderBy = "timemodified";
$OrderByFields = "filename,userid,filesize,timemodified,timecreated";
$extensionOut = "html";
$b = $bC = $h1 = $h1C = $h3 = $h3C = $table = $tableC = $tr = $td = $td2 = $td3 = $tdL = $tdR = "";
$tdC = " ";
$br = $hr = $trC = "\n"; 
// In cli-mode ?
if ( $isCli )
{	$opts = getopt("e::hi::l::stw::o::");  //getopt(null, ["i:"]);
	// select Moodle instance
	if ( isset( $opts["e"] ) && $opts["e"] )
	{	$filterExt = strtolower( $opts["e"] ); }
	if ( isset( $opts["h"] ) )
	{	$help = true; }
	if ( !empty( $opts["i"] ) )
	{	$instance = $opts["i"]; }
	if ( isset( $opts["l"] ) && intval( $opts["l"] )>1 )
	{	$limit = intval( $opts["l"] ); }
	if ( isset( $opts["o"] ) AND !empty( $opts["o"] ) )
	{	$orphaned = true; }
	if ( isset( $opts["s"] ) )
	{	$show  = true; }
	if ( isset( $opts["t"] ) )
	{	$extensionOut = "txt"; }
	if ( isset( $opts["w"] ) )
	{	$extensionOut = "html"; }
} 
else 
{	// Not in cli-mode
	$isWeb = $isHTML = true;
	$extensionOut = "html";
	if ( !isset( $_REQUEST ) || !isset( $_REQUEST["analyzer_form"]) || isset( $_REQUEST["help"]) )
	{	$help = true; }
	if ( isset( $_REQUEST["instance"]) && $_REQUEST["instance"] )
	{	$instance = $_REQUEST["instance"]; }
	if ( isset( $_REQUEST["orphaned"]) && $_REQUEST["orphaned"] )
	{	$orphaned = $_REQUEST["orphaned"]; }
	if ( isset( $_REQUEST["orderby"]) && $_REQUEST["orderby"] )
	{	$OrderBy = strtolower($_REQUEST["orderby"]); 
		if ( !in_array( $OrderBy, explode(",", $OrderByFields) ) )
		{	$OrderBy = "timemodified"; }
	}
	if ( isset( $_REQUEST["ext"]) && $_REQUEST["ext"] )
	{	$filterExt = strtolower($_REQUEST["ext"]); }
	if ( isset( $_REQUEST["mimetype"]) && $_REQUEST["mimetype"] )
	{	$filterMime = strtolower($_REQUEST["mimetype"]); }
	if ( isset( $_REQUEST["dateFrom"]) && $_REQUEST["dateFrom"] )
	{	$filterDateFrom = $_REQUEST["dateFrom"]; }
	if ( isset( $_REQUEST["dateUntil"]) && $_REQUEST["dateUntil"] )
	{	$filterDateUntil = $_REQUEST["dateUntil"]; }
	if ( isset( $_REQUEST["filename"]) && !empty($_REQUEST["filename"]) )
	{	$filterFilename = strtolower($_REQUEST["filename"]); }
	if ( isset( $_REQUEST["UserID"]) && !empty($_REQUEST["UserID"]) )
	{	$filterUserID = $_REQUEST["UserID"]; }
	if ( isset( $_REQUEST["CourseID"]) && !empty($_REQUEST["CourseID"]) )
	{	$filterCourseID = $_REQUEST["CourseID"]; }
	if ( isset( $_REQUEST["CourseShortname"]) && !empty($_REQUEST["CourseShortname"]) )
	{	$filterCourseShortname = $_REQUEST["CourseShortname"]; }
	if ( isset( $_REQUEST["limit"]) && intval($_REQUEST["limit"])>0 )
	{	$limit = $_REQUEST["limit"]; }
	if ( isset( $_REQUEST["show"]) && !empty($_REQUEST["show"]) )
	{	$show = true; }
	if ( $filterExt == "pdf" || empty($filterExt) )
	{	if ( isset( $_REQUEST["pdfImage"]) && !empty($_REQUEST["pdfImage"]) )
		{	$pdfImage = true; }
		if ( isset( $_REQUEST["pdfImageOnly"]) && !empty($_REQUEST["pdfImageOnly"]) )
		{	$pdfImageOnly = true; $pdfImage = true; $filterExt = "pdf"; }
		if ( isset( $_REQUEST["pdfBookScannerOnly"]) && !empty($_REQUEST["pdfBookScannerOnly"]) )
		{	$pdfBookScannerOnly = true; $pdfImage = true; $pdfImageOnly = true;  $filterExt = "pdf";}
	}
}

if ( empty( $instance ) )
{	$instance = "moodle_production"; }

if ( $extensionOut == "html" )
{	$isHTML = true;
	$table = "<table class=\"showgrid\">\n"; $tableC = "</table>\n"; $tr = "<tr>\n"; $trC = "</tr>\n"; 
	$td = "<td>\n"; $td2 = "<td colspan=\"2\">\n"; $td3 = "<td colspan=\"3\">\n"; $tdC = "</td>\n"; $tdR = '<td style="text-align:right">'."\n";
	$b = "<b>"; $bC = "</b>"; $br = "<br>\n"; $hr="<hr>\n"; $h1 = "<h1>\n"; $h1C = "</h1>\n"; $h3 = "<h3>\n"; $h3C = "</h3>\n";
}


//  if called outside of Moodle
if ( !$isWeb || !isset( $CFG ) || !is_object( $CFG ) )
{	$dirroot = "/data/var/www/$instance";
	//define('CLI_SCRIPT',true);
	require_once ($dirroot.'/config.php');
}
else
{   require_once ($CFG->dirroot.'/config.php');
    require_once($CFG->libdir . '/adminlib.php'); 
	 //remove Moodle sidebars and footer
	 // To be Done!!
}


// debugging
$debug = 1;
if ( $debug ) { 
    error_reporting(63);
    ini_set("display_errors", 1);
    ini_set("display_startup_errors", 1); 
    ini_set("log_errors", 1);
}	
// output buffering
ini_set("output_buffering", 1200);
// set timeout  - php-fpm should be set to 0 for request_terminate_timeout
set_time_limit(300);



// help
if ( $help )
{	if (  $isWeb )
	{	HTMLheader();
		$moodle_instance = "moodle_" . ( strstr( $CFG->wwwroot ,"8088") ? "staging" : ( strstr( $CFG->wwwroot ,"88") ? "dev" :"production") );
		echo '
			<form method="POST">
			<input type="hidden" name="analyzer_form" value="1">
			<table>
			<tr><td>Moodle Instance: </td><td><input type="text" name="instance" value="'.$moodle_instance.'" style="width:170px;"></td></tr>			
			<!--tr><td>Show orphaned files: </td><td><input type="checkbox" name="orphaned" value="1" style="width:20px;"></td></tr-->
			<tr><td>Limit records: </td><td><input type="text" name="limit" style="width:45px;"></td></tr>
			<tr><td title="Possible sort fields: '.$OrderByFields.'">Order by: </td><td><input type="text" name="orderby" value="date" style="width:120px;"></td></tr>
			<tr><td>Date from: </td><td><input type="text" name="dateFrom" value="'.date("d.m.Y",strtotime("first day of -1 month")).'" style="width:120px;"></td></tr>
			<tr><td>Date until: </td><td><input type="text" name="dateUntil" value="'.date("d.m.Y").'" style="width:120px;"></td></tr>
			<tr><td>Extension: </td><td><input type="text" name="ext" value="" placeholder="pdf" style="width:120px;"></td></tr>
			<tr><td>Mime-Type: </td><td><input type="text" name="mimetype" value=""  placeholder="video" style="width:120px;"></td></tr>
			<tr><td>Show file list: </td><td><input type="checkbox" name="show" value="1" checked="checked" onclick="/*togglePDF("div_pdf");*/"></td></tr>
			<tr><td>Eval. PDF Image Filters: </td><td><input id="pdfImage" name="pdfImage" type="checkbox" value="1"> (<500 files/minute)</td></tr>
			<tr><td>Only PDF files with images: </td><td><input id="pdfImageOnly" name="pdfImageOnly" type="checkbox" value="1"></td></tr>
			<tr><td>Only PDF files from book scanner: </td><td><input id="pdfBookScannerOnly" name="pdfBookScannerOnly" type="checkbox" value="1"> (A4 with 5% tolerance)</td></tr>
			<tr><td>File Name: </td><td><input type="text" name="filename" value="" style="width:120px;"></td></tr>
			<tr><td>User ID: </td><td><input type="text" name="UserID" value="" style="width:120px;"></td></tr>
			<tr><td>Course ID: </td><td><input type="text" name="CourseID" value="" style="width:120px;"></td></tr>
			<tr><td>Course Shortname: </td><td><input type="text" name="CourseShortname" value="" style="width:120px;"></td></tr>
			</table>
			<input style="font-size:120%;font-weight:800;" type="submit" value="Start">
			</form>';
			echo "{$b}Stored Analyzer Results$bC:$br";
			showResultFiles( $outFolder );
			echo $h1.$AppName.": {$b}Parameters$bC".$h1C."
			instance=Moodle Instance (default: moodle_production)$br
			orphaned=0/1 (Show orphaned files and option to remove them)
			limit=nnn (default: unlimited records to evaluate, useful for test purposes)$br
			dateFrom=date starting from date (default: All dates)$br
			orderby=Sort order. (default: date)$br
			dateUntil=date limit evaluation to date (default: All dates)$br
			ext=extension (default: PDF)$br
			mimetype=MIME-TYPE (default: All Mime Types)$br
			filename=only files matching filename (default: All files)$br
			UserID=only files from UserID (default: All files)$br
			show=show file list (default: show file list)$br
			help - this page";
			HTMLfooter();
			return;			
	}
	else
	{	echo $AppName.": Usage
				-h this page
				-i Moodle Instance (default: moodle_production)
				-l limit=nnn (default: unlimited records to evaluate)
				-o show orphaned file list ith option to remove (default: not show)
				-s show file list (default: not show)
				-t output to text file
				-w output to html (default)\n\n"; 
		exit;
	}
}
elseif ( $isWeb )
{	echo HTMLheader(); }


global $DB;
// connect to database
// db Table(s)
$tableFrom = "mdl_files f";



// search / remov orphaned files
if ( $orphaned)
{	$output = $retval = null;
	$filefolder = "/data/var/lib/$instance-data/filedir";
	$cmd = "/usr/bin/find $filefolder -type f -exec ls -hal '{}' \;";
	exec($cmd, $output, $retval);
	if ( count($output) < 3 )
	{	echo "${br}Error searching for files in $filefolder: Status $retval and output:$br";
		print_r($output); 
		print "${br}Exec: $cmd${br}";
		exit; 
	}
	print "${h3}Files in filedir: " .count($output) .$h3C;
	// get table hashes
	$query = "SELECT DISTINCT ON (contenthash) AS contenthash FROM {files} ORDER BY contenthash ;";
	$result = $DB->get_records_sql($query);
	if (!$result) 
	{	echo "$br$b"."An error occurred with query '$query'$bC$br";
		if ( $isCli )
		{	exit; }
		else 
		{	return; }
	}	
	print "${h3}Files in Moodle tble files: " .count($result) .$h3C;
	print substr(nl2br(var_export($result,true)),0,2000);
	exit;
}






// db Table(s)
$tableFrom = "{files} AS f";

// set filters
$filter = $filterText = "";
if ( $filterExt )
{	$filter = " AND f.filename ILIKE  '%.$filterExt' "; 
	$filterText = $br.$b."Evaluation Filter{$bC}: Files with extension .$filterExt". 
						 ($pdfBookScannerOnly? " -only PDF files from book scanner"
					  : ($pdfImageOnly? " -only PDF files with images "
					  : ($pdfImage ? " -with image analysis " :""))) ;
}
if ( $filterMime )
{	$filterText = ($filterText?$filterText." AND ":$br."<b>Evaluation Filter</b>: Files with ") . "mimetype = $filterMime";
	$filter .= " AND f.mimetype iLIKE '%$filterMime%' "; 
}
if ( $filterDateFrom )
{	$filterText = ($filterText?$filterText." AND ":$br."<b>Evaluation Filter</b>: Files with ") . "last modification date >= $filterDateFrom";
	$filter .= " AND f.timemodified>=".strtotime($filterDateFrom)." "; 
}
if ( $filterDateUntil )
{	$filterText = ($filterText?$filterText." AND ":$br."<b>Evaluation Filter</b>: Files with ") . "last modification date <= $filterDateUntil";
	$filter .= " AND f.timemodified<=".strtotime($filterDateUntil)." "; 
}
if ( $filterUserID )
{	$filterText = ($filterText?$filterText." AND ":$br."<b>Evaluation Filter</b>: Files with ") . "userid = $filterUserID";
	$filter .= " AND f.userid = $filterUserID "; 
}
if ( $filterFilename )
{	$filterText = ($filterText?$filterText." AND ":$br."<b>Evaluation Filter</b>: Files with ") . "'$filterFilename' is part of file name";
	$filter .= " AND f.filename iLIKE '%$filterFilename%' "; 
}
if ( $filterCourseID || $filterCourseShortname )
{	if ( $filterCourseID )
	{	$filterText = ($filterText?$filterText." AND ":$br."<b>Evaluation Filter</b>: Files ") . "used by courses with id: '$filterCourseID'";
		$filter .= " AND c.id = $filterCourseID "; 
	}
	if ( $filterCourseShortname )
	{	$filterText = ($filterText?$filterText." AND ":$br."<b>Evaluation Filter</b>: Files ") . "used by courses with shortname: '$filterCourseShortname'";
		$filter .= " AND c.shortname = '$filterCourseShortname' "; 
	}
	$tableFrom = 	"{files} AS f
						INNER JOIN {context} ct ON f.contextid = ct.id
						INNER JOIN {resource} rs ON ct.instanceid = rs.id
						INNER JOIN {course} c ON rs.course = c.id
						INNER JOIN {course_modules} cm ON c.id = cm.course";
}

$starttime = time();

// collect some general data
$query = "SELECT COUNT(*) AS count, SUM(filesize) as size FROM (
		 SELECT DISTINCT(contenthash) contenthash,filename,filesize,filearea,mimetype,timemodified
		 FROM {files} where filesize>0 AND component != 'core' ORDER BY contenthash ) distinct_hash;";
$result = $DB->get_record_sql($query);
if (!$result) 
{	echo "$br$b"."An error occurred with query '$query'$bC$br";
	if ( $isCli )
	{	exit; }
	else 
	{	return; }
}

$repoRows = $result->count;
$repoSize = $result->size;
// now run the main query
$query = "SELECT * FROM (
            SELECT DISTINCT(f.contenthash) f.contenthash AS contenthash, f.filename AS filename, f.filesize AS filesize, f.filearea AS filearea,
			f.mimetype AS mimetype,f.timemodified AS timemodified, f.userid AS userid, f.author AS author, f.license AS license " .
			( stristr( $tableFrom, "inner j") ?", c.idnumber AS idnumber, c.shortname AS shortname ": " ").
			"FROM $tableFrom where f.filesize>0 AND f.component != 'core' $filter ORDER BY f.contenthash ) distinct_hash 
			ORDER by distinct_hash.$OrderBy DESC;";
// 			ORDER by distinct_hash.timemodified DESC;";
$result = $DB->get_records_sql( $query);
if (!$result) {
	echo "$br$b"."An error occurred with query '$query'$bC$br";
	if ( $isCli )
	{	exit; }
	else 
	{	return; }
}

$resultRows = count ( $result );
$resultRowsPC = round($resultRows/100,0);
$dataroot = $CFG->dataroot;
$tmpout = tempnam("/tmp","mdl");
$outfile = $outFolder.$instance."_files";
$outfile .= ( $filterFilename ? "_".$filterFilename :"" );
$outfile .= ( $filterExt ? "_".$filterExt :"" );
if ( $filterExt == "pdf" && $pdfImage )
{	$outfile .= ( $pdfBookScannerOnly ?"_book_scanner" :($pdfImageOnly?"_only_with_images" :"_with_images_eval")); }
$outfile .= ( $filterDateFrom ? "_".$filterDateFrom :"" );
$outfile .= ( $filterDateUntil ? "_".$filterDateUntil :"" );
$outfile .= ( $filterUserID ? "_ID-".$filterUserID :"" );
$outfile .= ( $limit<$resultRows ? "_limit-".$limit :"" );

$outfilePDF = $outfile.".pdf";
$outfile .= ".".$extensionOut;


if ( $isCli )
{	$htmlHeader = $htmlFooter = $tableStyle = ""; }
$evalLimit = $limit<$unlimited && $limit<$resultRows?$limit:$resultRows;
$speedDiv = $filterExt!="pdf" ?1.5: (!$pdfImage?3:21);
echo 	$br.$b."Evaluating data of ".($limit<$unlimited?"first $limit of ":"").number_formatDE($resultRows).
		" distinct files for Moodle instance $instance.$bC". 
		($evalLimit>(4000/$speedDiv)?"This may take up to ". number_formatDE( $evalLimit/(4000/$speedDiv))." minutes.":"").
		'<span id="starteval">'.$br.'Results will be shown on completion'.$br.'</span>
		<span style="font-size:125%;font-weight:bold;" id="stopwatch">&nbsp;</span>'.$filterText.$br;
$pdfImageNote = $br;
if ( $pdfImage )
{	$pdfImageNote = $b."Note$bC: Assumed book scans without Text/OCR are marked in ".'<b style="color:crimson">crimson'.$bC.
						", book scans with Text/OCR are marked in ".'<b style="color:maroon">maroon'.$bC.$br; 
	echo $pdfImageNote; 
}

// stopwatch for time elapsed
?>
<script>
var stopwatch = document.getElementById('stopwatch'),
    seconds = 0, minutes = 0, hours = 0;
function stopWatch() {
    seconds++;
    if (seconds >= 60) { seconds = 0; minutes++; if (minutes >= 60) { minutes = 0; hours++; } }
    stopwatch.innerHTML = (hours ? (hours > 9 ? hours : "0" + hours) : "00") + ":" + (minutes ? (minutes > 9 ? minutes : "0" + minutes) : "00") + ":" + (seconds > 9 ? seconds : "0" + seconds);
}
function setTimer()
{	let timerid = setInterval(stopWatch, 1000); }
stopWatch();
setTimer();
</script>
<?php

$count = $pdfCount = $totalsize = $totalPDF = $totalPDFpages = $totalPDFimages = $ppiCount = $ppiTotal = $totalBookScans = 
			$totalOCRbookScans = $toBeIgnored = 0;
$mimetypes = $mimetype_sizes = $pdf_versions = $pdf_version_sizes = $pdf_version_Pages = $pdf_version_bookScans = array();

$line = "";
$showline = "$table$tr$td$b" . "File Name" ."$bC$tdC$td$b". "Extension" ."$bC$tdC$td$b". "Last Modified" ."$bC$tdC$tdR$b". "Size" .
				($filterUserID ?"$bC$tdC$td$b". "UserID" :"").
				($filterCourseID ?"$bC$tdC$tdR$b". "idnumber" :"").($filterCourseShortname ?"$bC$tdC$td$b". "Shortname" :"").
				"$bC$tdC$td$b" . "Mime Type" ."$bC$tdC$td$b". "Version" ."$bC$tdC$tdR$b". "Pages" .
				( $pdfImage ?"$bC$tdC$tdR$b". "Images" ."$bC$tdC$tdR$b". "PPI":"") ."$bC$tdC$td$b". "Page Size" .
				"$bC$tdC$trC";

@ob_flush();@ob_end_flush();@flush();
foreach ( $ressult AS $row )
{ 	set_time_limit(180);
    if ($count>=$limit){
        break;
    }
    $count ++;
	$filename = trim( $row['filename'] );
	$filesize = $row['filesize'];
	$cHash    = $row['contenthash'];
	$mimetype = trim( $row['mimetype'] ); 
	$last_modified = date( "d.m.Y", $row['timemodified'] );
	$filepath = $dataroot . "/filedir/" . substr($cHash,0,2) . "/" . substr($cHash,2,2) . "/" . $cHash; 
	$idnumber = isset( $row['idnumber'] ) ?$row['idnumber'] :0;
	$shortname = isset( $row['shortname'] ) ?$row['shortname'] :0;
	$userID = trim( $row['userid'] ); 
	$author = trim( $row['author'] ); 
	$license = trim( $row['license'] ); 
	$pdfPPI = $bookscanner = $pdfImages = $pdfPages = 0; 
	$pageFormat = $pdfPagesHtml = $pdfImagesHtml = $pdfVersion = "&nbsp;";

	if ( empty ( $mimetype ) )
	{	$mimetype = "undefined"; }
	
	if ( stristr( $mimetype, 'pdf' ) )
	{	if ( is_file( $filepath ) )
		{	$pdfInfo = trim( shell_exec( "/usr/bin/pdfinfo $filepath 2>/dev/null|/usr/bin/ack -i 'Pages:\s+(\d+)|Page size:\s+(.+)|PDF version:\s+(.+)'  --output '$1$2$3'") ); 
			//$pdfInfo = str_replace( "  ", "\n", $pdfInfo );
			//echo "<hr>pdfInfo:$pdfInfo<hr>"; exit;
			$pdfInfoA = explode( "\n", $pdfInfo ); 
			$pdfVersion = "PDFinfo Error";
			if ( count( $pdfInfoA ) >= 3 && strlen( $pdfInfo ) < 35 )
			{	list($pdfPages, $pageFormat, $pdfVersion) = $pdfInfoA; 
				$pdfPages = intval( trim($pdfPages) ); $pageFormat = trim($pageFormat); $pdfVersion = trim( $pdfVersion );	
			}
		}
		else
		{	$pdfVersion = "file not found"; }
		$pdfPagesHtml = $pdfPages;
		$pdfImagesHtml = $pdfPPI = $isBookScan = 0;
		if ( !isset( $pdf_versions["$pdfVersion"] ) )
			{	$pdf_versions["$pdfVersion"] = $pdf_version_sizes["$pdfVersion"] = $pdf_version_Pages["$pdfVersion"] = 
				$pdf_version_bookScans["$pdfVersion"] = $pdf_version_OCRbookScans["$pdfVersion"] = 0; }
		if ( $pdfImage )
		{	$pdfHasImages = strlen( trim( shell_exec( "/bin/grep -a '/Image' $filepath 2>/dev/null"))) >10;
			if ( $pdfHasImages)
			{	$pdfImageData = getPDFImageData( $filepath );
				if ( count( $pdfImageData ) > 0 )
				{	$pdfImages = count( $pdfImageData );  
					$pdfImagesHtml = $pdfImages;
					$pdfPPI = getPDFImagePPI( $filepath, $pdfImageData ); 
				}
			}
			
			// is book scanner used? A4: 595 x 842 - Toleranz: 10%
			//preg_match( "/([0-9]+\.*[0-9]*) x ([0-9]+\.*[0-9]*)(.*)/i", $pageFormat, $matches );
			preg_match( "/(.+)\s*x\s*(.+) (.*)/i", $pageFormat, $matches );
			if ( isset($matches[2]) && isset($matches[3]) )
			{	$size1 = intval(min($matches[1],$matches[2])); $size2 = intval(max($matches[1],$matches[2]));
				$pageFormat = intval($matches[1]) . "x" . intval($matches[2]) ." ". trim($matches[3]);
				$isBookScan = 	($pdfImages == $pdfPages) && ( 
									( (595*0.95 <= $size1) && ($size1 <= 598*1.05) && (842*0.95 <= $size2) && ($size2 <= 842*1.05) ) 
									|| stristr( $pageFormat, "A4" ) );
			}
			if ( $isBookScan ) 
			{	$pdfNoText = strlen( trim( shell_exec( "/bin/grep -a '/Text' $filepath|grep -ai '/Font' 2>/dev/null"))) <80;
				if ( $pdfNoText )
				{	$pdf_version_bookScans["$pdfVersion"] ++; $totalBookScans ++; 
					$pdfImagesHtml = '<span style="color:crimson;font-weight:bold;">'.$pdfImages.'</span>';
					$pdfPagesHtml = '<span style="color:crimson;font-weight:bold;">'.$pdfPages.'</span>';
				}
				else
				{	$pdf_version_OCRbookScans["$pdfVersion"] ++; $totalOCRbookScans ++; 
					$pdfImagesHtml = '<span style="color:maroon;font-weight:bold;">'.$pdfImages.'</span>';
					$pdfPagesHtml = '<span style="color:maroon;font-weight:bold;">'.$pdfPages.'</span>';
				}
			}
			elseif ( $pdfImages == $pdfPages )
			{	$pdfImagesHtml = '<span style="color:darkgreen;font-weight:bold;">'.$pdfImages.'</span>';
				$pdfPagesHtml = '<span style="color:darkgreen;font-weight:bold;">'.$pdfPages.'</span>';
			}
		}
		$toBeIgnored = $pdfImage && $pdfImageOnly && (!$pdfImages || ( $pdfBookScannerOnly && !$isBookScan ) );
		if ( !$toBeIgnored )
		{	$pdfCount ++;
			$totalPDF += $filesize;
			$totalPDFpages += $pdfPages;
			$pdf_versions["$pdfVersion"] ++;
			$pdf_version_sizes["$pdfVersion"] += $filesize; 
			$pdf_version_Pages["$pdfVersion"] += $pdfPages;
			$totalPDFimages += $pdfImages;
			$ppiCount ++; $ppiTotal += $pdfPPI;
		}
	}
	$saveLines = false;
	if ( $resultRowsPC>0 )
	{	$percent = intval($count/$resultRowsPC);
		$saveLines = ($count/$resultRowsPC) == $percent;
	}
	if ( $saveLines ) 
	{	if ( $percent<2 )
		{	echo $b.'<span id="percent" style="font-size:200%;">'.$percent."</span>%".$bC; }
		else
		{	echo "<script>document.getElementById('percent').innerHTML = '$percent';</script>"; }
        // print "\n<script>window.scrollTo(0,document.body.scrollHeight);</script>\n";
        @ob_flush();@ob_end_flush();@flush();@ob_start();

    }
	if ( $toBeIgnored )
	{ 	continue; }

	$totalsize += $filesize;
	if ( !isset( $mimetype_sizes["$mimetype"] ) )
	{	$mimetype_sizes["$mimetype"] = $mimetypes["$mimetype"] = 0; }
	$mimetype_sizes["$mimetype"] += $filesize; 
	$mimetypes["$mimetype"] ++; 

	$filebase = basename($filename);
	$extPos = strrpos( $filebase, ".");
	if ( $extPos === false ) 
	{	$filebase =  substr( $filebase,0,40); $extension = ""; }
	else
	{	$extension = substr( substr( $filebase, $extPos+1 ),0,5); $filebase =  substr( substr( $filebase, 0, $extPos ),0,40); }
	
	if ( $isHTML )
	{	$filebase = "<a href=\"$selfURL?file=$filename&filepath=$filepath&mimetype=$mimetype&author=$author&license=$license&last_modified=$last_modified\" title=\"$filename\"target=\"FileInfo\">$filebase</a>"; 
		if ( strlen($mimetype) > 25 )
		{ $mimetype = '<span title="'.$mimetype.'">'.substr($mimetype,0,25).'</span>'; }
	}
	else
	{	$filebase = str_pad($filebase,42); }
	
	$showline .= "$tr$td".$filebase  ."$tdC$td". str_pad($extension,5) ."$tdC$td". $last_modified ."$tdC$tdR".
				str_pad(number_formatDE($filesize/1024),12," ",STR_PAD_LEFT) . " KB" .
				($filterUserID ?"$bC$tdC$td". $userID :"").
				($filterCourseID ?"$tdC$tdR". $idnumber :"").($filterCourseShortname ?"$tdC$td". $shortname :"").
				"$tdC$td". $mimetype ."$tdC$td". $pdfVersion ."$tdC$tdR". $pdfPagesHtml ."$tdC". 
				( $pdfImage ? $tdR. $pdfImagesHtml .$tdC. $tdR. $pdfPPI .$tdC:"") 
				. $td. $pageFormat .$tdC. $trC;
	$line .= $showline;
	if ( $saveLines && $show ) 
	{	file_put_contents ( $tmpout, $line, FILE_APPEND ); 
		$line = "";
	}
	if ( !$show )
	{	$line = ""; }
	$showline = ""; 
} // end loop

if ( $show )
{	$line .= $tableC;
	echo $tableC;
    file_put_contents ( $tmpout, $line, FILE_APPEND );
	$line = "";
}

// sort result arrays
ksort( $pdf_versions );
ksort( $mimetypes );
echo "<script>document.getElementById('percent').innerHTML = '100';</script>";

// summary data
$line = $br.$h1."ASH Berlin - Moodle Files$h1C";
$line .= "Moodle Instance:$b $instance$bC  -  {$b}Number of valid files evaluated{$bC}: ".number_formatDE($count).($limit<$unlimited?" (".number_formatDE($limit)." of "
	.number_formatDE($resultRows)." files were  evaluated)$br":"  -  ").$b."Sorted by: $bC".($OrderBy="timemodified"?"Date":$OrderBy)." - List creation date: ".date("d.m.Y H:s") ."$filterText$br$pdfImageNote$table";

$line .= "$tr$td".str_pad("Valid files in Repo:",35) ."$tdC$tdR". str_pad(number_formatDE($repoRows),10," ",STR_PAD_LEFT ) ."$tdC$tdR". 
	 str_pad(number_formatDE($repoSize/1024/1024),10," ",STR_PAD_LEFT ) ." MB$tdC$trC";

$line .= "$tr$td".str_pad("Evaluated files:",35) ."$tdC$tdR". 
			str_pad(($limit<$unlimited?"$limit/":"").number_formatDE($resultRows),10," ",STR_PAD_LEFT ) ."$tdC$tdR". 
	 str_pad(number_formatDE($totalsize/1024/1024),10," ",STR_PAD_LEFT ) ." MB$tdC$trC";


if ( empty($filterExt) || $filterExt == "pdf" )
{
	$line .= "$tr$td".str_pad("Evaluated PDF files:",35) ."$tdC$tdR". str_pad(number_formatDE($pdfCount),10," ",STR_PAD_LEFT ) ."$tdC$tdR". 
				str_pad(number_formatDE($totalPDF/1024/1024),10," ",STR_PAD_LEFT ) ." MB$tdC$trC";

	$line .= "$tr$td2". str_pad("PDF versions found:",35) ."$tdC$tdR". str_pad(number_formatDE(count($pdf_versions)),10," ",STR_PAD_LEFT ) 
				. "$tdC$trC";

	$line .= "$tr$td2".str_pad("PDF Page Count",35) ."$tdC$tdR". 
				str_pad(number_formatDE($totalPDFpages),10," ",STR_PAD_LEFT ) ." $tdC$trC";

// pdf images evaluated
if ( $pdfImage )
{	$line .= "$tr$td2".str_pad("PDF Images Count",35) ."$tdC$tdR". 
				str_pad(number_formatDE($totalPDFimages),10," ",STR_PAD_LEFT ) ." $tdC$trC";
	$line .= "$tr$td2".str_pad("PDF average Image Resolution",35) ."$tdC$tdR". 
				str_pad(number_formatDE(intval($ppiTotal/max(1,$ppiCount))),10," ",STR_PAD_LEFT ) ." $tdC$trC";
	$line .= "$tr$td2".str_pad("PDF Image Book Scans",35) ."$tdC$tdR". 
				str_pad(number_formatDE($totalBookScans),10," ",STR_PAD_LEFT ) ." $tdC$trC";
	$line .= "$tr$td2".str_pad("PDF Image OCR Book Scans",35) ."$tdC$tdR". 
				str_pad(number_formatDE($totalOCRbookScans),10," ",STR_PAD_LEFT ) ." $tdC$trC";
}

echo "$tr$td3$table";
	 	
	$line .= "$tr$td$b" . "PDF Version" . "$bC$tdC$tdR$b" . "Count" . "$bC$tdC$tdR$b" . "Total Size" . "$bC$tdC$tdR$b" . "Total Pages" . "$bC$tdC";
	if ( $pdfImage )	{	$line .= $tdR.$b . "Image Book Scans" . "$bC$tdC" . $tdR.$b . "Image OCR Book Scans" . "$bC$tdC"; }
	$line .= "$trC";
	foreach ( $pdf_versions as $key => $val )
	{	$line .= "$tr$td - " . str_pad("$key:",35) ."$tdC$tdR". str_pad(number_formatDE($val),10," ",STR_PAD_LEFT ) 
					."$tdC$tdR". str_pad(number_formatDE($pdf_version_sizes["$key"]/1024/1024),10," ",STR_PAD_LEFT ) 
					." MB$tdC$tdR". str_pad(number_formatDE($pdf_version_Pages["$key"]),10," ",STR_PAD_LEFT ) ."$tdC";
		if ( $pdfImage )
		{	$line .= $tdR. str_pad(number_formatDE($pdf_version_bookScans["$key"]),10," ",STR_PAD_LEFT ) . $tdC; 
			$line .= $tdR. str_pad(number_formatDE($pdf_version_OCRbookScans["$key"]),10," ",STR_PAD_LEFT ) . $tdC; 
		}
		$line .= "$trC";
	}
	$line .= $tableC.$tdC.$trC;
}

if ( $filterExt !== "pdf" )
{
	$line .= "$tr$td2". str_pad("Mime types found:",35) ."$tdC$tdR". str_pad(number_formatDE(count($mimetypes)),10," ",STR_PAD_LEFT ) . "$tdC$trC$tr$td3$table";
	$line .= "$tr$td$b" . "Mime Type" . "$bC$tdC$tdR$b" . "Count" . "$bC$tdC$tdR$b" . "Total Size" . "$bC$tdC$trC";
	foreach ( $mimetypes as $key => $val )
	{	$line .= "$tr$td - " . str_pad(substr("$key:",0,53),55) ."$tdC$tdR". str_pad(number_formatDE($val),10," ",STR_PAD_LEFT ) ."$tdC$tdR".
					str_pad(number_formatDE($mimetype_sizes["$key"]/1024/1024),10," ",STR_PAD_LEFT ) ." MB$tdC";
		$line .= $trC;
	}

	$line .= $tableC.$tdC.$trC;
}
$line .= $tableC;
$time_used = $br."Script execution time: " . number_formatDE(time() - $starttime,0) . " seconds";
$line .= $time_used . "$br";
// print summary
echo $line;

// create output file
file_put_contents ( $outfile, $htmlHeader.$tableStyle.$line );

if ( $show ) { exec( "/bin/cat $tmpout>>$outfile"); }

file_put_contents ( $outfile, $htmlFooter, FILE_APPEND );
$LinkOutfilePDF = $outfile;
if (  $isHTML )
{	$LinkOutfilePDF = "<a href=\"".$ResultsURL.basename($outfilePDF)."\" target=\"mdl_files\">$outfilePDF</a>"; }
echo $br."See all results in file: $b$LinkOutfilePDF$bC$br";

shell_exec('/usr/local/bin/wkhtmltopdf --dpi 300 --page-size "A4" --title "ASH Moodle Files" --encoding utf-8 --minimum-font-size 12 "'.$outfile.'" "'.$outfilePDF.'"> /dev/null 2>&1 &'); 
if ( $show ) { require_once($tmpout); }
unlink($tmpout);

?>
<script>clearInterval(timerid);</script>
<style>#stopwatch, #starteval{display:none;}</style>
<?php
HTMLfooter();
