<?php
while(ob_get_level()>0) ob_get_clean();

/*---CONFIG---*/
$maskfiles=array('.php','.htm','.html','.tpl','.txt','.inc','.js'); //файлы, включающие эти слова идут в поиск
$minsearch=2; //минимальное количество символов для поиска
$maxfilesize=500*1024; //Максимальный размер файла (в байтах) по умолчанию: 500кб (только для нормального поиска без системных вызовов)
$exectime=180; //максимальное время работы варвары
$db_host='localhost';
$db_user='root';
$db_pass='root';
/*---/CONFIG---*/

session_start();

if (isset ( $_REQUEST ['exectime'] ))
	$exectime = $_REQUEST ['exectime'];

ini_set ( 'memory_limit', '128M');
ini_set ( 'display_errors', '1' );
error_reporting(E_ALL);
ini_set("max_execution_time", $exectime);

header('Content-Type: text/html; charset=utf-8',true);

$text = '';
if (isset ( $_REQUEST ['text'] ))
	$text = $_REQUEST ['text'];
$text=str_replace('\"','"',$text);
$text=str_replace("\'","'",$text);
$atext=str_replace('"','&quot;',$text);

$slow = '';
if (isset ( $_REQUEST ['slow'] ))
	$slow = $_REQUEST ['slow'];
$enc = '';
if (isset ( $_REQUEST ['enc'] ))
	$enc = $_REQUEST ['enc'];
$mask = '';
if (isset ( $_REQUEST ['mask'] ))
	$mask = $_REQUEST ['mask'];
$old_search = '';
if (isset ( $_REQUEST ['old_search'] ))
	$old_search = $_REQUEST ['old_search'];
$strip = '1';
if (isset ( $_REQUEST ['strip'] ))
	$strip = $_REQUEST ['strip'];
if (isset ( $_REQUEST ['masks'] ))
	$maskfiles = explode(";",$_REQUEST ['masks']);
$dir = '';
if (isset ( $_REQUEST ['dir'] ))
	$dir = $_REQUEST ['dir'];
$where = '';
if (isset ( $_REQUEST ['where'] ))
	$where = $_REQUEST ['where'];
$step = '1';
if (isset ( $_REQUEST ['step'] ))
	$step = $_REQUEST ['step'];
if (isset ( $_REQUEST ['db_host'] ))
	$db_host = $_REQUEST ['db_host'];
if (isset ( $_REQUEST ['db_user'] ))
	$db_user = $_REQUEST ['db_user'];
if (isset ( $_REQUEST ['db_pass'] ))
	$db_pass = $_REQUEST ['db_pass'];

$matches = 0;
global $matches,$slow,$enc,$mask,$maskfiles,$maxfilesize,$db_host,$db_user,$db_pass;

echo '
<html>
<head>
<title>VaRVaRa Searcher</title>
<meta http-equiv="content-type" content="text/html; charset=utf-8" />
<style type="text/css">
body {
	text-size: 10px;
	padding: 5px;
}
.searchdiv {
	border: 1px solid black;
	padding: 5px;
	margin: 5px;
}
.founddiv {
	border: 1px solid black;
	padding: 5px;
	margin: 1px;
	background-color: #EEEEEE;
	color: black;
}
.sqldiv {
	border: 1px solid black;
	padding: 5px;
	margin: 5px;
	background-color: #AAFFAA;
	color: black;
}
#loading{
	border: 1px solid black;
	padding: 20px;
	display: block;
	text-align: center;
	margin: 0 auto;
	width: 300px;
	background-color: #FFFFFF;
}
</style>
<script>
function passspoiler()
{
	var sp=document.getElementById("pass_spoiler");
	if(sp.style.display==\'none\')
		sp.style.display=\'block\';
		else
		sp.style.display=\'none\';
}
</script>
</head>
<body>
<div class="searchdiv">
<form action="" method="get">What are you like, Master?: <input
	type="text" name="text"
	value="'.$atext.'" size="100"> <input type="submit" value="Go!"> <br>
	<table cellspacing="0" cellpadding="0" border="0">
		<tr>
			<td>
	<input type="checkbox" name="enc" value="1" '; if ($enc) echo "checked"; echo'>CP1251
	<input type="checkbox" name="mask" value="1" '; if ($mask || !isset($_REQUEST['text'])) echo "checked"; echo '>Маска
	<input type="text" name="masks" value="'.implode(";",$maskfiles).'">
	<input type="checkbox" name="strip" value="1" '; if ($strip) echo "checked"; echo '>Срезать теги в SQL
<br>
	<input type="radio" name="where" value="name" '; if($where=="name") echo "checked"; echo '>Имя файла</option>
	<input type="radio" name="where" value="file" '; if(!isset($_REQUEST['where']) || $where=="file") echo "checked"; echo '>Внутри файла</option>
	<input type="radio" name="where" value="sql" '; if($where=="sql") echo "checked"; echo '>SQL</option>
	</select>
	<input type="checkbox" name="old_search" value="1" '; if ($old_search) echo "checked"; echo'>Старый поиск
	</td>
	<td width="10">&nbsp;</td>
	<td>
		<input type="button" value="&gt;" onClick="passspoiler();">
	</td>
	<td>
		<table cellspacing="0" cellpadding="0" border="0" id="pass_spoiler" style="display: none;">
		<tr>
			<td>
				<div>
					Host: <input type="text" name="db_host" value="'.$db_host.'"><br>
					User: <input type="text" name="db_user" value="'.$db_user.'"><br>
					Pass: <input type="text" name="db_pass" value="'.$db_pass.'"><br>
				</div>
			</td>
			<td>
				Время работы: <input type="text" name="exectime" value="'.$exectime.'" size="1"><br>
				<input type="checkbox" name="slow" value="1" '; if ($slow) echo "checked"; echo '>Размытый поиск(медленно)<br>
				Путь поиска<input type="text" name="dir" size="60" value="'; if($dir) echo $dir; else { $mainpath=pathinfo($_SERVER['SCRIPT_FILENAME']); echo $mainpath['dirname']; } echo '">
			</td>
		</tr>
		</table>
	</td>
	</tr>
	</table>
</form>
</div>
';

if($step=="result")
{
	echo "Founded in:<br>";
	echo $_SESSION['echo'];
	//echo "Matches: " . $_SESSION['matches'] . "<br>";
	$_SESSION['echo']="";
	exit();
}

if (strlen ( $text ) >= $minsearch) {
	echo '<div id="loading" align="center"><img src="http://tip.ie/img/loading.gif" align="middle">Подождите, идёт поиск...</div>';
	if($step==1)
	{
		$_SESSION['files']=array();
		$_SESSION['matches']=0;
		$_SESSION['echo']="";
		$_SESSION['outp']=-1;
	}
	if($where=="file")
	{
		if($step==1 && !$old_search)
		{
			if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN')
			{
				$_SESSION['outp']=-1;
				echo "<script>document.location.href=document.location.href+'&step=2'</script>";
				exit();
			}

			$masks="";
			if($mask)
				foreach($maskfiles as $msk)
					if(trim($msk)!="")
						$masks.=" --include=*.".str_replace(".","",$msk);
			$comm="grep -rlI $masks ".escapeshellarg($text)." $dir 2>/dev/null";
			if (PHP_OS === 'FreeBSD')
				$comm="grep -rlI ".escapeshellarg($text)." $dir 2>/dev/null";
			//echo $comm;
			$outp=wsoEx($comm);
			//echo PHP_OS; print_r($outp); exit();
			$_SESSION['outp']=$outp;
			echo "<script>document.location.href=document.location.href+'&step=2'</script>";
			exit();
		}
		$outp=$_SESSION['outp'];
		if($old_search)
		{
			$outp=-1;
			if($step==1)
			{
				echo "<script>document.location.href=document.location.href+'&step=2'</script>";
				exit();
			}
		}
		if($outp!=-1)
		{
			foreach ( $outp as $addr )
				if(trim($addr)!='')
					search_in_file ( $addr, $text );
			echo "<script>document.location.href=document.location.href.replace('step=2','step=result');</script>";
			exit();
		}
		else
		{
			if($step==2)
			{
				$_SESSION['files'] = search_files ( $dir );
				echo "<script>document.location.href=document.location.href.replace('step=2','step=3');</script>";
				exit();
			}
			if($step==3)
			{
				if ($slow)
					$text = trimpage ( $text );
				foreach ( $_SESSION['files'] as $addr )
					if(trim($addr)!='')
						search_in_file ( $addr, $text );
				echo "<script>document.location.href=document.location.href.replace('step=3','step=result');</script>";
				exit();
			}
		}
	}
	if($where=="sql")
	{
		sql_search($text);
		echo "<script>document.location.href=document.location.href+'&step=result'</script>";
		exit();
	}
	if($where=="name")
	{
		$_SESSION['files'] = search_files ( $dir );
		foreach ( $_SESSION['files'] as $addr )
			if(trim($addr)!='')
				if(strpos(strtolower($addr), strtolower($text))!==false)
				{
					$_SESSION['echo'].='<div class="founddiv">' . $addr . '</div>';
					$_SESSION['matches'] ++;
				}
		echo "<script>document.location.href=document.location.href+'&step=result'</script>";
		exit();
	}
}
else
	if(isset($_REQUEST['text']))
		echo "Слишком короткая строка поиска!";

function wsoEx($in) {
	$out = '';
	if (function_exists('exec')) {
		@exec($in,$out);
	} elseif (function_exists('passthru')) {
		ob_start();
		@passthru($in);
		$out = ob_get_clean();
		$out=explode("\n",$out);
	} elseif (function_exists('system')) {
		ob_start();
		@system($in);
		$out = ob_get_clean();
	} elseif (function_exists('shell_exec')) {
		$out = shell_exec($in);
		$out=explode("\n",$out);
	} elseif (is_resource($f = @popen($in,"r"))) {
		$out = "";
		while(!@feof($f))
			$out .= fread($f,1024);
		pclose($f);
		$out=explode("\n",$out);
	}
	else return -1;
	return $out;
}


function sql_search($text)
{
	global $db_host,$db_user,$db_pass,$slow,$strip;
	$link=mysql_connect($db_host,$db_user,$db_pass) or die(mysql_error());
	if($link)
	{
		$q=mysql_query("SET NAMES UTF8",$link);
		$q=mysql_query("SHOW DATABASES",$link);
		$databases=array();
		while($tmp=mysql_fetch_array($q)){ if($tmp[0]!='information_schema') $databases[]=$tmp[0]; }
		foreach($databases as $db)
		{
			$q=mysql_query("USE `".$db."`",$link);
			$q=mysql_query("SHOW TABLES");
			$tables=array();
			while($tmp=mysql_fetch_array($q)){ $tables[]=$tmp[0]; }
			foreach($tables as $table)
			{
				$q=mysql_query("DESCRIBE ".$table);
				$fields=array();
				if($slow)
					while($tmp=mysql_fetch_array($q)){ $fields[]=$tmp[0]; }
				else
					while($tmp=mysql_fetch_array($q)){ if(strpos($tmp[1],"char")!==false || strpos($tmp[1],"text")!==false) $fields[]=$tmp[0]; }
					$fld="";
					foreach($fields as $field)
						$fld.="`".$field."` LIKE '%".$text."%' OR ";
					$q=mysql_query("SELECT * FROM `".$table."` WHERE $fld 1=0");
					$results=array();
					while($tmp=mysql_fetch_assoc($q)){ $results[]=$tmp; }
					if(count($results)>0)
					{
						$_SESSION['files'][]=$table;
						$_SESSION['echo'].='<div class="sqldiv">';
						$_SESSION['echo'].="<font color='blue'>Founded In: DB(".$db.")->Table(".$table.")</font><br>";
						$_SESSION['echo'].='<table border="1"><tr>';
						foreach($results[0] as $f=>$k)
						{
							$_SESSION['echo'].="<td>".$f."</td>";
						}
						$_SESSION['echo'].="</tr>";
						foreach ($results as $result)
						{
							$_SESSION['echo'].='<tr valign="top">';
							foreach($result as $f=>$k)
							{
								if($strip)
									$_SESSION['echo'].="<td>".htmlspecialchars($k)."</td>";
								else
									$_SESSION['echo'].="<td>".$k.$strip."</td>";
								$_SESSION['matches']++;
							}
							$_SESSION['echo'].="</tr>\n";
						}
						$_SESSION['echo'].="</table></div>";
					}
			}
		}
	}
}

function trimpage($page) {
	$page = trim ( $page );
	$page = str_replace ( "\n", "", $page );
	$page = str_replace ( "\r", "", $page );
	$npage = str_replace ( "  ", " ", $page );
	while ( $npage != $page ) {
		$page = $npage;
		$npage = str_replace ( "  ", " ", $page );
	}
	return $page;
}

function regexp($text) {
	$subj = str_replace ( " ", ' *', $text );
	$subj = "%" . $subj . "%siU";
	return $subj;
}

function search_in_file($path, $subj) {
	global $matches,$slow,$enc;
	$path=str_replace('//','/',$path);
	$file = file_get_contents ( $path );
	if($enc)
		$file = enc_text_to_utf($file);
	if($slow)
	{
		$file = trimpage ( $file );
		$file=mb_convert_case($file, MB_CASE_LOWER);
		$subj=mb_convert_case($subj, MB_CASE_LOWER);
	}
	if (strpos ( $file, $subj ) !== false) {
		$add="";
		$pl="";
		$f=fopen($path,"r");
		while($l=fgets($f))
		{
			if($enc)
				$l = enc_text_to_utf($l);
			$x = strpos ( $l, $subj );
			if($x !== false)
			{
				if($x>100) $x=$x-100;
				else $x=0;
				$pl=substr($pl,0,200);
				$l=substr($l,$x,200);
				$l=htmlspecialchars($pl)."<br>".htmlspecialchars($l);
				$l=str_replace($subj,"<font color='red'>".$subj."</font>",$l);
				if(strlen($_SESSION['echo'])<=1048560)
					$add.='<div class="sqldiv">' . $l. '</div>';
			}
			$pl=$l;
		}
		fclose($f);
		$_SESSION['echo'].='<div class="founddiv">' . $path . $add. '</div>';
		$_SESSION['matches'] ++;
	}
}

function enc_text_to_utf($text){
	$text=@iconv("WINDOWS-1251","UTF-8",$text);
	return $text;
}

function search_files($path) {
	global $mask,$maskfiles,$maxfilesize;
	$result = array ();
	if (!is_dir($path))
	{
		if($mask)
		{
			$skip=true;
			foreach($maskfiles as $msk)
				if(strpos(strtolower($path),strtolower($msk))!==false)
					$skip=false;
			if(!$skip && filesize($path)<=$maxfilesize)
				return $path;
		}
		else
			if(filesize($path)<=$maxfilesize)
				return $path;
	}
	else
	{
		$dir = dir ( $path );
		if($dir)
		while ( false !== ($entry = $dir->read ()) )
			if ($entry != "." && $entry != "..")
			{
				$entry = search_files ( $path . '/' . $entry );
				if (is_array ( $entry ))
					$result = array_merge ( $result, $entry );
				else
					$result [] = $entry;
			}
	}
	return $result;
}
echo '
</body>
</html>
';
?>
