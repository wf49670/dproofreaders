<?
$relPath="./../../pinc/";
include_once($relPath.'v_site.inc');
include_once($relPath.'prefs_options.inc'); // PRIVACY_*
include_once($relPath.'connect.inc');
include_once($relPath.'xml.inc');
include_once($relPath.'page_tally.php');
include_once('../includes/team.php');
include_once('../includes/member.php');
$db_Connection=new dbConnect();

if (empty($_GET['username'])) {
	include_once($relPath.'theme.inc');
	theme("Error!", "header");
	echo "<br><center>A username must specified in the following format:<br>$code_url/stats/members/mbr_xml.php?username=*****</center>";
	theme("", "footer");
	exit();
}

//Try our best to make sure no browser caches the page
header("Content-Type: text/xml");
header("Expires: Sat, 1 Jan 2000 05:00:00 GMT");
header("Last-Modified: " . gmdate("D, d M Y H:i:s") . "GMT");
header("Cache-Control: no-cache, must-revalidate");
header("Pragma: no-cache");

$result = mysql_query("SELECT * FROM users WHERE username = '".$_GET['username']."' LIMIT 1");
$curMbr = mysql_fetch_assoc($result);
$result = mysql_query("SELECT * FROM phpbb_users WHERE username = '".$curMbr['username']."'");
$curMbr = array_merge($curMbr, mysql_fetch_assoc($result));

$rankArray = get_user_page_tally_rank_array($curMbr['username'], FALSE);
$neighbors = user_get_page_tally_neighbors($rankArray, 4);

$bestDay = bestDayEver($curMbr['u_id']);

$now = time();
$daysInExistence = floor(($now - $curMbr['date_created'])/86400);
if ($daysInExistence > 0) {
	        $daily_Average = $curMbr['pagescompleted']/$daysInExistence;
} else {
		$daily_Average = 0;
}

	

$data = '';

//User info portion of $data
if ($curMbr['u_privacy'] == PRIVACY_PUBLIC)
{
	$data = "<userinfo id=\"".$curMbr['u_id']."\">
			<username>".xmlencode($curMbr['username'])."</username>
			<datejoined>".date("m/d/Y", $curMbr['date_created'])."</datejoined>
			<lastlogin>".date("m/d/Y", $curMbr['last_login'])."</lastlogin>
			<pagescompleted>".$curMbr['pagescompleted']."</pagescompleted>
			<overallrank>".$rankArray['curMbrRank']."</overallrank>
			<bestdayever>
				<pages>".$bestDay['count']."</pages>
				<date>".$bestDay['time']."</date>
			</bestdayever>
			<dailyaverage>".number_format($daily_Average)."</dailyaverage>
			<location>".xmlencode($curMbr['user_from'])."</location>
			<occupation>".xmlencode($curMbr['user_occ'])."</occupation>
			<interests>".xmlencode($curMbr['user_interests'])."</interests>
			<website>".xmlencode($curMbr['user_website'])."</website>
		</userinfo>
	";

//Team info portion of $data
	$result = mysql_query("SELECT id, teamname, active_members, page_count FROM user_teams WHERE id = ".$curMbr['team_1']." || id = ".$curMbr['team_2']." || id = ".$curMbr['team_3']."");
	$data .= "<teaminfo>";
	while ($row = mysql_fetch_assoc($result)) {
		$data .= "<team>
		<name>".xmlencode($row['teamname'])."</name>
		<pagescompleted>".$row['page_count']."</pagescompleted>
		<activemembers>".$row['active_members']."</activemembers>
		</team>
		";
	}
	$data .= "</teaminfo>";


//Neighbor info portion of $data
	$data .= "<neighborinfo>";
	foreach ( $neighbors as $rel_posn => $neighbor )
	{
		$result = mysql_query("SELECT date_created FROM users WHERE username = '".$neighbor->get_username()."'");

		$data .= "<neighbor>
			<rank>".$neighbor->get_current_page_tally_rank()."</rank>
			<username>".xmlencode($neighbor->get_username())."</username>
			<datejoined>".date("m/d/Y", mysql_result($result, 0, "date_created"))."</datejoined>
			<pagescompleted>".$neighbor->get_current_page_tally()."</pagescompleted>
		</neighbor>
		";
	}


	$data .= "</neighborinfo>
	";
}
else
{
	$data = '';
}

$xmlpage = "<"."?"."xml version=\"1.0\" encoding=\"$charset\" ?".">
<memberstats xmlns:xsi=\"http://www.w3.org/2000/10/XMLSchema-instance\" xsi:noNamespaceSchemaLocation=\"memberstats.xsd\">
$data
</memberstats>";

echo $xmlpage;
?>
