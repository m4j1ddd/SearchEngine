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
<title><?php if(trim($search) != "") echo $search." - جستجوی "; ?>عکس های <?php echo $main_title; ?> Photos</title>
<meta name="description" content="بخش عکس های موتور جستجوی فارسی میهن جو امکان جستجو و دانلود عکس های جدید یا قدیمی ایرانی و خارجی را برای شما فراهم می کند" />
<meta name="keywords" content="موتور جستجو, جستجو, میهن جو, میهن, جو, ایران, ایرانی, فارسی, جستجوی فارسی, جستجوی عکس, دانلود عکس, دانلود, عکس, عکس جدید, عکس ایرانی" />
<link rel="stylesheet" type="text/css" href="../style.css">
<link rel="shortcut icon" href="/images/favicon.ico" />
<script type="text/javascript" src="../js.js"></script>
</head>
<body onLoad='ajaxFunction();'>
<?php
menu('/photos/', $search);
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
 foreach($d as $index => $value)
 {
	  $url = $index;
	  $query = "select * from links
		        where url = '$url' and type like 'image/%'";
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
		 if(preg_match_all('/\s+'.$ind.'\s+/si', " ".$row[3]." ",$mat))
		 {
			@ $de[$link] += 5*$value;
		 }
		 else
		 {
			 @ $de[$link] += $value;
		 }
		}
	   }
	  } 
 }
 arsort($de);
 $num = 20;
 $j = 1;
 $count = count($de);
 if($count == 0) echo "نتیجه ای یافت نشد"; else echo $count." نتیجه<br>";
 foreach($de as $index=>$value)
 {
	 if($j>($page-1)*$num && $j<= $page*$num)
	 {
		 if(@ $fp = fopen($index, "r"))
		 {
		  fclose($fp);
	      ?>
		  <a href="<?php echo $index ?>"><img src="<?php echo $index ?>"></a>
          <?php
		 }
		 else {
			 $query = "delete from links where link = '$index'";
			 mysqli_query($db, $query);
		 }
	 }
	 $j++;
 }
 page($search, $page, $count, $num);
 ?></div><?php
}
@ mysqli_close($db);
foot();
?>
</body>
</html>