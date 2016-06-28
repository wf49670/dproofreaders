<?php
// Display lists of image sources, or lists of projects that used image sources
// List contents vary with user permissions

$relPath='../../pinc/';
include_once($relPath.'base.inc');
include_once($relPath.'theme.inc');
include_once($relPath.'project_states.inc');
include_once($relPath.'dpsql.inc');
include_once($relPath.'misc.inc'); // array_get()
include_once($relPath.'pg.inc');

require_login();

$theme_args['css_data'] = "
h1 { margin-top: 1em; }
table.individual { border-collapse: collapse; width: 80%; margin: auto; }
table.individual td,th { border: 1px solid #999; padding: 5px; }
table.individual td.center { text-align: center; }
table.individual th { background-color: #eeeeee; }
.headerbg { background-color: ". $theme['color_logobar_bg'] . "; }

table.listing { width: 90%; margin: auto; border: solid black 1px; border-collapse: collapse; }
table.listing td,th { padding: 5px; }
table.listing th { background-color: #eeeeee; }
table.listing th + td { vertical-align: top; border: none; }
table.listing th.tl { vertical-align: top; text-align: left; border: none;}
table.listing td.center { text-align: center; }
tr.first { border-top: double black; }
.fullname { font-size: 120%; }
.sourcelink { font-size: 90%; margin: 10px 0px 3px 25px; }
.w15 { width: 15%; }
";

$which = get_enumerated_param($_GET, 'which', 'DONE', array('ALL', 'DONE', 'INPROG'));

$locuserSettings =& Settings::get_Settings($pguser);

// ---------------------------------------
// Page construction varies with whether the user is logged in or out
if (isset($GLOBALS['pguser'])) { $logged_in = TRUE;} else { $logged_in = FALSE;}

if ($logged_in)
{
    if  ( user_is_image_sources_manager() ) {
        $min_vis_level = 0;
    } else if (user_is_PM()) {
        $min_vis_level = 1;
    } else {
        $min_vis_level = 2;
    }
}
else
{
    $min_vis_level = 3;
}


if (!isset($_GET['name']))
{
    $header_text = _("Image Sources");
    output_header($header_text, NO_STATSBAR, $theme_args);

    echo "<h1>{$header_text}</h1>\n";

    if (user_is_PM()) {
        echo "<p><a href='projectmgr.php'>"._("Back to your PM page")."</a></p><br><br>";
    }

    $query = mysql_query("
        SELECT * FROM
        (
            SELECT * FROM image_sources
            WHERE info_page_visibility >= $min_vis_level
        ) i
        LEFT JOIN
        (
            SELECT
            image_source,
            count(distinct  projectid) as projects_total,
            sum(".SQL_CONDITION_GOLD.") as projects_completed
            FROM projects
            WHERE state != 'project_delete'
            AND image_source != ''
            GROUP BY image_source
        ) p
        ON (p.image_source = i.code_name)
        GROUP BY code_name
        ORDER BY display_name");

    echo "<table border='1' class='listing'>\n";

    // Column Headers
    echo "<tr>\n";
    echo "<th class='w15'>" . _("Name in Dropdown") . "</th>";
    echo "<th colspan='2'>" . _("Image Source Details") . "</th>\n";
    echo "<th class='w15'>" . _("Works: In-Progress / Completed / Total") . "</th>\n";
    echo "</tr>\n";

    while ( $row = mysql_fetch_assoc($query) )
    {
        echo "<tr class='first'>\n";
        echo "<td rowspan='4' class='center'>{$row['display_name']}";
        // Show the status if source is not enabled
        // KEY: -1 = Pending Review, 0 = Disabled, 1 = Enabled
        if ($row['is_active'] != 1)
        {
            $status_text = "";
            echo "<br><br>";
            echo "<small>";
            if ($row['is_active'] == -1)
                $status_text = _("Pending Review");
            if ($row['is_active'] == 0)
                $status_text = _("Disabled");
            echo "[{$status_text}]</small>";
        }
        echo "</td>\n";

        echo "<th class='tl'>" . _("Full Name:") . "</th>\n";

        $source_fullname = $row['full_name'];
        // Since we apparently allow an empty full_name, check for that
        // and try to recover by using the display name, flagged with
        // an asterisk so even if display_name is blank, we get a visible
        // indication of the issue (and a visible link when applicable).
        if ($row['full_name'] == '' )
        {
            $source_fullname .= "<span title='"
                . attr_safe(_("Record is missing a value for Full Name; Display Name used instead."))
                . "'>*</span>" . $row['display_name'];
        }

        // The second test in the following line is 'magic'
        // Due to a bug in manage_image_sources.php, a value of 'http://'
        // used to be stored in the url column if no URL was entered.
        if ( (!is_null($row['url'])) && ($row['url'] != 'http://') )
            $link_name = "<br><a class='sourcelink' href='{$row['url']}'>{$row['url']}</a>";
        else
            $link_name = "";

        echo "<td class='headerbg'><span class='fullname'>$source_fullname</span> ${link_name}</td>";

        if (is_null($row['projects_total']))
            $row['projects_total'] = 0;
        if (is_null($row['projects_completed']))
            $row['projects_completed'] = 0;
        $projects_inprogress = $row['projects_total'] - $row['projects_completed'];

        echo "<td rowspan='4' class='center'>";
        $p_link = $projects_inprogress;
        if ($projects_inprogress > 0)
        {
            $p_link = "<a href='show_image_sources.php?name="
            . $row['code_name']
            . "&which=INPROG'>$projects_inprogress</a>";
        }
        echo $p_link;
        echo " / "; // This is a divider slash between counts
        $c_link = $row['projects_completed'];
        if ($row['projects_completed'] > 0)
        {
            $c_link = "<a href='show_image_sources.php?name="
            . $row['code_name']
            . "&which=DONE'>{$row['projects_completed']}</a>";
        }
        echo $c_link;
        echo "<br><br>";
        $t_link = $row['projects_total'];
        if ($row['projects_total'] > 0)
        {
            $t_link = "<a href='show_image_sources.php?name="
            . $row['code_name']
            . "&which=ALL'>{$row['projects_total']}</a>";
        }
        // TRANSLATORS: %s is a number
        echo sprintf(_("%s in total"),$t_link);
        echo "</td>\n";
        echo "</tr>\n";

        echo "<tr>\n";
        echo "<th class='tl'>" . _("Image Policies:") . "</th>\n";
        echo "<td>";
        switch ($row['ok_show_images'])
        {
            case 1:
            {
                echo _("Images can be published.");
                break;
            }
            case 0:
            {
                echo _("Images cannot be published.");
                break;
            }
            case -1:
            default:
            {
                echo _("Image publishing policy is unknown.");
                break;
            }
        }
        echo " "; // Space between policy statements
        switch ($row['ok_keep_images'])
        {
            case 1:
            {
                echo _("Images can be stored.");
                break;
            }
            case 0:
            {
                echo _("Images cannot be stored.");
                break;
            }
            case -1:
            default:
            {
                echo _("Image storage policy is unknown.");
                break;
            }
        }
        echo "</td>";
        echo "</tr>\n";

        echo "<tr>\n";
        echo "<th class='tl'>" . _("Description:") . "</th>\n";
        echo "<td>{$row['public_comment']}</td>";
        echo "</tr>\n";

        // Preserving the if ($logged_in) logic may seem pointless, but
        // in the event we decide to remove the login requirement at the
        // top of the file, we'll want the same behavior back.
        // The former behavior was:
        // if ($logged_in)
        //     sort by display_name and include the internal_comment
        // else
        //     sort by the full_name and do not display internal_comment
        // For now, we'll simply suppress the internal_comment.

        echo "<tr>\n";
        echo "<th class='tl'>" . _("Notes:") . "</th>\n";
        echo "<td>";
        if ($logged_in)
            echo $row['internal_comment'];
        echo "</td>";
        echo "</tr>\n";
    }

    echo "</table>\n";

} else {

    $imso_code = $_GET['name'];

    $imso = mysql_fetch_assoc( mysql_query("
        SELECT
            full_name,
            display_name,
            public_comment,
            internal_comment,
            info_page_visibility,
            concat('<a href=\"',url,'\">',url,'</a>') as 'info_url'
        FROM image_sources
        WHERE code_name = '$imso_code'
    "));

    $visibility = $imso['info_page_visibility'];


    // info page visibility
    //  0 = Image Source Managers and SAs
    //  1 = also any PM
    //  2 = also any logged-in user
    //  3 = anyone

    // layout below intended to make logic easier to follow:
    // see it as a tree lying on it's side, the root node the "or"
    // on the third line

    $can_see = (
                ($visibility == 3)
            or
                ($logged_in
                    and
                    (
                        $visibility == 2
                        or
                        (user_is_PM() and $visibility == 1)
                        or
                        user_is_image_sources_manager()
                    )
                )
            );

    if ($can_see) {

        $base_link = "<a href='show_image_sources.php?name=%s&which=%s'>%s</a>";
        $all_link = sprintf($base_link, $imso_code, "ALL", _("All"));
        $inprog_link = sprintf($base_link, $imso_code, "INPROG", _("In-Progress"));
        $done_link = sprintf($base_link, $imso_code, "DONE", _("Completed"));

        switch ($which)
        {
            case 'ALL':
                $where_cls = " AND state != 'project_delete'";
                $title = sprintf( _("All Ebooks being produced with images from %s"), $imso['full_name'] );
                $links_list = $inprog_link . ", " . $done_link . ".";
                break;
            case 'INPROG':
                $where_cls = " AND state != 'project_delete' AND state != 'proj_submit_pgposted'";
                $title = sprintf( _("In-Progress Ebooks being produced with images from %s"), $imso['full_name'] );
                $links_list = $all_link . ", " . $done_link . ".";
                break;
            case 'DONE':
            default:
                $where_cls = " AND  ".SQL_CONDITION_GOLD." ";
                $title = sprintf( _("Completed Ebooks produced with images from %s"), $imso['full_name'] );
                $links_list = $inprog_link . ", " . $all_link . ".";
        }

        $description = $imso['public_comment'];
        $internal_notes = $imso['internal_comment'];
        $info_url = $imso['info_url'];

        output_header($title, NO_STATSBAR, $theme_args);

        echo "<h1>$title</h1>\n";

        echo "<p>";
        echo sprintf(
            _("See other lists of Ebooks being produced with images from %s: "),
                $imso['full_name']);
        echo $links_list;
        echo "</p>";

        echo  "<p><a href='show_image_sources.php'>"
            . _("Back to the full listing of all Image Sources")
            . "</a></p>";

        echo "<table class='individual'>\n";
        echo "<tr>";
        echo "<td colspan='5' class='headerbg'>";
        echo "<h2>{$imso['full_name']}</h2>";
        echo "<p><b>" . _("URL:") . "</b> $info_url</p>\n\n";

        echo "<p><b>" . _("Description:") . "</b> $description</p>\n";

        if ($logged_in)
        {
            echo "<p><b>" . _("Internal Notes:") . "</b> $internal_notes</p>\n";
        }
        echo "</td></tr>\n";

        $result = mysql_query(sprintf("
            SELECT
                projectid, nameofwork, authorsname,
                genre, language, postednum
            FROM projects
            WHERE image_source = '%s' ".$where_cls."
            ORDER BY nameofwork
            ", mysql_real_escape_string($imso_code)));

        echo "<tr>";
        echo "<th>" . _("Title") . "</th>";
        echo "<th>" . _("Author") . "</th>";
        echo "<th>" . _("Genre") . "</th>";
        echo "<th>" . _("Language") . "</th>";
        if ($which != "INPROG")
            echo "<th>" . _("PG Number") . "</th>";
        echo "</tr>\n";

        while ( $row = mysql_fetch_assoc($result) )
        {
            echo "<tr>\n";
            echo "<td>";
            echo "<a href='$code_url/project.php?id=" .
                $row['projectid'] . "'>" . $row['nameofwork'] . "</a>";
            echo "</td>";
            echo "<td>";
            echo $row['authorsname'];
            echo "</td>";
            echo "<td class='center'>";
            echo $row['genre'];
            echo "</td>";
            echo "<td class='center'>";
            echo $row['language'];
            echo "</td>";

            // For In-Progress, suppress final column since it conveys no info
            if ($which != "INPROG")
            {
                echo "<td class='center'>";
                if (!is_null($row['postednum']))
                {
                    echo "<a href='{$PG_home_url}ebooks/"
                    . $row['postednum'] . "'>" . $row['postednum'] . "</a>";
                }
                else
                {
                    echo _("In Progress");
                }
                echo "</td>";
            }
            echo "</tr>\n";
        }
        echo "</table>\n";

    } else {

        $title = _("Unknown Image Source Code");
        output_header($title, NO_STATSBAR);

        echo "<h1>$title</h1>\n";

        echo  "<p><a href='show_image_sources.php'>"._("Back to the full listing of Image Sources")."</a></p><br><br>";

    }
}

echo "<br>\n";

// vim: sw=4 ts=4 expandtab
