<?php
require("../functions.php");
$search = "";
$page = 1;
if(isset($_GET['search'])) $search = $_GET['search'];
if(isset($_GET['page'])) $page = $_GET['page'];
?>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" >
<title><?php if(trim($search) != "") echo $search." - جستجوی "; ?>آهنگ های <?php echo $main_title; ?> Songs</title>
<meta name="description" content="بخش آهنگ های موتور جستجوی فارسی میهن جو امکان جستجو و دانلود آهنگ های جدید یا قدیمی ایرانی و خارجی را برای شما فراهم می کند" />
<meta name="keywords" content="موتور جستجو, جستجو, میهن جو, میهن, جو, ایران, ایرانی, فارسی, جستجوی فارسی, جستجوی آهنگ, دانلود آهنگ, دانلود, آهنگ, آهنگ جدید, آهنگ ایرانی" />
<link rel="stylesheet" type="text/css" href="../style.css">
<link rel="shortcut icon" href="/images/favicon.ico" />
<script type="text/javascript" src="../js.js"></script>
</head>
<body onLoad='ajaxFunction();'>
<?php
menu('/songs/', $search);
if(trim($search) != "")
{
 ?><div class="body"><?php
 $d = array();
 $searchk = preg_split('/[\s,\-\.]+/si', $search);
 foreach($searchk as $index)
 {
  if(trim($index) != "")
  {
	$query = "select * from keywords
	          where keyword = '$index'";
	$result = mysqli_query($db, $query);
	$c = mysqli_num_rows($result);
	for($i = 1; $i<=$c; $i++)
	{
		$row = mysqli_fetch_row($result);
		$url = $row[0];
		$density = $row[2];
		@ $d[$url] += $density;
	}
  }	 
 }
 $de = array();
 $ipage = array();
 foreach($d as $index => $value)
 {
	  $url = $index;
	  $query = "select * from links
	 	        where url = '$url' and type like 'audio/%'";
	  $result = mysqli_query($db, $query);
	  $c = mysqli_num_rows($result);
	  for($i=1; $i<=$c; $i++)
	  {
	   $row = mysqli_fetch_row($result);
	   foreach($searchk as $ind)
	   {
	    if(trim($ind) != "" && trim($row[1]) != "")
		{
		 $link = $row[1];
		 $ipage[$link] = $row[0];
		 if(preg_match_all('/\s+'.$ind.'\s+/si', " ".$row[3]." ",$mat))
		 {
			@ $de[$link] += 5*$value;
		 }
		 else
		 {
			 @$de[$link] += $value;
		 }
		}
	   }
	  } 
 }
 arsort($de);
 $num = 10;
 $j = 1;
 $count = count($de);
 if($count == 0) echo "نتیجه ای یافت نشد"; else echo $count." نتیجه<br>";
 ?><table><?php
 foreach($de as $index=>$value)
 {
	 if($j>($page-1)*$num && $j<= $page*$num)
	 {
		 if(@ $fp = fopen($index, "r"))
		 {
		  fclose($fp);
		  $url = $ipage[$index];
	      if(@ $read = file_get_contents($url))
		  {
			 $url = urldecode($url);
			 $read = preg_replace("/<script.*?>.*?<\/script.*?>/si", " ", $read);
             $read = preg_replace("/<style.*?>.*?<\/style.*?>/si", " ", $read);
			 $description = "";
             $regex = '/<meta\s+.*?name\s*=\s*[\'"]description[\'"].*?content\s*=\s*[\'"](.*?)[\'"].*?>/si';
             if(preg_match_all($regex, $read, $mat)) $description = $mat[1][0];
             else
             {
	          $regex = '/<meta\s+.*?content\s*=\s*[\'"](.*?)[\'"].*?name\s*=\s*[\'"]description[\'"].*?>/si';
	          if(preg_match_all($regex, $read, $mat)) $description = $mat[1][0];
             }
			 $r = preg_replace("/&.*?;/si"," ", preg_replace('/<[^>]*>/',' ',$read));
			 $r = trim($r);
			 $description .= " ".$r;
			 $description = trim($description);
			 $ldescription = strtolower($description);
			 $ar_des = explode(' ', $description);
			 $lar_des = explode(' ', $ldescription);
	         $keys_des = array();
	         foreach($searchk as $ind)
	         {
			  $ind = strtolower($ind);
	          $keys_des = array_merge($keys_des, array_keys($lar_des, $ind));
	         }
	         sort($keys_des);
			 $parsed = parse_url($index);
			 $path = pathinfo($parsed['path']);
			 ?>
             <tr>
             <td>نام: <a class="result" href="<?php echo $index ?>"><?php echo urldecode($path['filename']) ?></a></td>
             <td>نوع: <?php echo $path['extension'] ?></td>
             <td>اندازه: <font style="direction: ltr; float:left;"><?php
             $bytes = remote_filesize($index);
             echo FileSizeConvert($bytes);
             ?></font>
             </td>
             <td>
             <a class="result" href="<?php echo $index ?>">دانلود</a>
             </td>
			 </tr>
             <tr>
             <td colspan="4">
             <?php echo show($keys_des, $ar_des) ?>
             </td>
             </tr>
             <tr>
             <td colspan="4" style="color: green; direction: ltr; float: right;">
			 <?php
			 echo $show_url = substr($url, 0, 70);
             if($show_url!=$url) echo "...";
			 ?>
             </td>
             </tr>
			 <?php
		  }
		  else {
			 $query = "delete from web where url = '$url'";
			 mysqli_query($db, $query);
			 $query = "delete from links where link = '$url'";
			 mysqli_query($db, $query);
			 $query = "delete from keywords where url = '$url'";
			 mysqli_query($db, $query);
		  }
		 }
		 else {
			 $query = "delete from links where link = '$index'";
			 mysqli_query($db, $query);
		 }
	 }
	 $j++;
 }
 ?></table><?php
 page($search, $page, $count, $num);
 ?></div><?php
}
@ mysqli_close($db);
foot();
?>
</body>
</html>