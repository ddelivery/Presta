<?xml version="1.0" encoding="utf-8"?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="charset=UTF-8" />
<meta http-equiv="content-language" content="ru" />
<meta name="keywords" content="" />
<meta name="description" content="" />
<meta name="robots" content="all" />
<meta name="author" content="" />
<meta name="copyright" content="" />
<meta name="resourceurl" content="" />
<meta name="publisher-url" content="" />
<title>{title}</title>

<link href="style.css" rel="stylesheet" type="text/css" />

<script type="text/javascript" src="js/jquery.min.js"></script>
<script type="text/javascript" src="js/effects.js"></script>

<!--[if lte IE 6]><script src="js/DD_belatedPNG.js"></script>
<script type="text/javascript">
  DD_belatedPNG.fix('div, span, img, a, ul li, input');
</script>
<![endif]-->
{script}
{xajaxscript}
</head>
<!--[if lt IE 7 ]> <body class="no-js ie6"> <![endif]-->
<!--[if IE 7 ]>    <body class="no-js ie7"> <![endif]-->
<!--[if IE 8 ]>    <body class="no-js ie8"> <![endif]-->
<!--[if (gte IE 9)|!(IE)]><!--> <body class="no-js"> <!--<![endif]-->

<div id="container">
<div class="yzor"></div>
<div id="header">
    <div class="lang">
        <form action="" method="get" name="LangForm" id="LangForm">
            <div>
                <select id="lang" name="lang" onchange='LangForm.submit();'>
                    <option value="ru">�������</option>
                    <option value="uk">����������</option>
                    <option value="en">����������</option>
                </select>
                <label for="lang">�������� ����:</label>
            </div>
        </form>
    </div>
    <div class="logo"><a href="/"><img src="images/logo.png" alt="" /></a></div>
    <div class="top_contacts">
        tel.: <span>+38044</span>3833683<br />
        e-mail: <a href="mailto:vip@viphalls.com.ua">vip<span>@</span>viphalls.com.ua</a>
    </div>
    <div class="clear"></div>
    <div class="top_menu">
        <div class="corner"></div>
        <ul>
            <li class="first"><a href="/">�������� ���-���</a></li>
            <li><a href="/transfers/">��������� � ������� �������</a></li>
            <li><a href="/scoreboard/">����� �������� � ������� � �/� �������</a></li>
            <li><a href="/contacts/">��������</a></li>
        </ul>
    </div>
</div>

<div id="content">
    <div class="left_column">
        
        <div class="left_menu">
            <ul>
                <li><a  href="/booking/kiev-borispol/">����-��������� �������� �</a></li>
                <li><a href="/booking/kiev-zhulyany/">����-������</a></li>
                <li><a href="/booking/kiev-borispol-zod/">����-��������� ���</a></li>
                <li><a href="/booking/doneck/">������</a></li>
                <li><a href="/booking/dnepropetrovsk/">��������������</a></li>
                <li><a href="/booking/odessa/">������</a></li>
                <li><a href="/booking/xarkov/">�������</a></li>
                <li><a href="/booking/simferopol/">�����������</a></li>
                <li><a href="/booking/lvov/">�����</a></li>
            </ul>
        </div>
        
        <div class="links_for_left">
            <ul>
                <li>
                    <div class="ukr"><a href="/organizaciyam-ukrainy/" class="active">������������ �������</a></div>
                </li>
                <li>
                    <div class="rus"><a href="/specproposition/">��������������� ��� ��. ��� ��</a></div>
                </li>
                <li>
                    <div class="tur"><a href="/turisticheskim-firmam-ukrainy/">������������� ������ �������</a></div>
                </li>
            </ul>
        </div>
    </div>    
    <div class="main_column">

        <h1>{header}</h1>
        
        <div class="breadcrumbs"><a href="/">�������</a> &nbsp;&nbsp;&rsaquo;&nbsp;&nbsp; <span>���-������</span></div>
        {content}
    </div>
    
</div>    
       
<div class="clear"></div>


<div id="footer">
    <div class="copyright">All rights reserved.  2012 VIPhalls<br />���������� ����� - <a href="#">White web</a></div>
</div>

</div>
</body>
</html>